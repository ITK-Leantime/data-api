<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\BadRequestException;
use Leantime\Plugins\APIData\Model\DeletedRequestParameters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeletedRequestParameters::class)]
final class DeletedRequestParametersTest extends TestCase
{
    /**
     * Each type is a table of its own, so there is nothing sensible to default to.
     * Omitting it used to be an undefined key followed by a foreach over null.
     */
    public function testOmittingTheTypeIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('type is required and must be one of: projects, milestones, tickets, timesheets.');

        DeletedRequestParameters::fromInput([]);
    }

    public function testAnEmptyTypeIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['type' => '']);
    }

    /**
     * `type[]=tickets&type[]=timesheets` is a caller asking for two pages at once.
     * Reducing it to the first element would serve a type it did not ask about.
     */
    public function testAListOfTypesIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('type must be a single value.');

        DeletedRequestParameters::fromInput(['type' => ['tickets', 'timesheets']]);
    }

    public function testWhitespaceAroundTheTypeIsIgnored(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => ' tickets ']);

        $this->assertSame('tickets', $parameters->type);
    }

    /**
     * An unknown type used to reach the repository's match arm, which threw with
     * the raw value in the message and rendered it on the error page.
     */
    public function testAnUnknownTypeIsRejectedWithoutEchoingTheInput(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('type must be one of: projects, milestones, tickets, timesheets.');

        DeletedRequestParameters::fromInput(['type' => '<script>']);
    }

    /**
     * `users` has no deleted-entities table, so it is not a valid type here even
     * though the entity endpoints support it.
     */
    public function testUsersIsNotASupportedDeletedType(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['type' => 'users']);
    }

    public function testAValidRequestFallsBackToTheDocumentedPagingDefaults(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'timesheets']);

        $this->assertSame('timesheets', $parameters->type);
        $this->assertSame(0, $parameters->start);
        $this->assertSame(DeletedRequestParameters::DEFAULT_LIMIT, $parameters->limit);
        $this->assertNull($parameters->deletedAfter);
    }

    /**
     * The endpoint is documented as GET with query parameters, so every value can
     * arrive as a string.
     */
    public function testNumericStringsFromAQueryStringBecomeIntegers(): void
    {
        $parameters = DeletedRequestParameters::fromInput([
            'type' => 'tickets',
            'start' => '25',
            'limit' => '50',
        ]);

        $this->assertSame(25, $parameters->start);
        $this->assertSame(50, $parameters->limit);
    }

    /**
     * Laravel ignores a negative limit, which drops the LIMIT clause and returns
     * the entire deletion history — the very thing pagination exists to prevent.
     */
    public function testANegativeLimitIsRejectedRatherThanReturningEveryRow(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'limit' => '-1']);
    }

    public function testAZeroLimitIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('limit must be at least 1.');

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'limit' => 0]);
    }

    /**
     * The effective limit is echoed back in the response parameters, so a capped
     * request is visible to the caller rather than silently different.
     */
    public function testALimitAboveTheMaximumIsCapped(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'limit' => 5000]);

        $this->assertSame(DeletedRequestParameters::MAX_LIMIT, $parameters->limit);
        $this->assertSame(DeletedRequestParameters::MAX_LIMIT, $parameters->toArray()['limit']);
    }

    public function testANegativeStartIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'start' => -5]);
    }

    public function testTheDeletedAfterTimestampIsNarrowedToAnInteger(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'deletedAfter' => '1759906882']);

        $this->assertSame(1759906882, $parameters->deletedAfter);
    }

    public function testANonNumericDeletedAfterTimestampIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('deletedAfter must be a whole number.');

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'deletedAfter' => 'last week']);
    }

    public function testAnEmptyDeletedAfterTimestampIsTreatedAsAbsent(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'deletedAfter' => '']);

        $this->assertNull($parameters->deletedAfter);
    }

    /**
     * Ignoring the retired name would answer with the whole deletion history while
     * the caller believes it asked for a window — which is how the consumer's
     * timestamp went missing when it sent `deletedAfter` to the old `deleted`.
     */
    public function testTheOldDeletedNameIsRejectedRatherThanIgnored(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('deleted has been renamed to deletedAfter.');

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'deleted' => '1759906882']);
    }

    /**
     * An empty value means the parameter was never really sent, so an old client's
     * bare `?deleted=` is not worth failing the request over.
     */
    public function testAnEmptyOldDeletedValueIsTreatedAsAbsent(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'deleted' => '']);

        $this->assertNull($parameters->deletedAfter);
    }

    /**
     * A caller still sending `types` has no `type`, so it would otherwise be met
     * with "type is required" — accurate, but silent about the rename that is the
     * only thing it has to act on.
     */
    public function testTheOldTypesNameIsRejectedByName(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('types has been replaced by type, which names exactly one type.');

        DeletedRequestParameters::fromInput(['types' => 'tickets']);
    }

    /**
     * The list form is what `types` was for, so it is the shape the old consumer
     * actually sends.
     */
    public function testTheOldTypesNameIsRejectedWhenSentAsAList(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('types has been replaced by type, which names exactly one type.');

        DeletedRequestParameters::fromInput(['types' => ['tickets', 'timesheets']]);
    }

    /**
     * Named alongside a valid `type` it is still the old parameter, and answering
     * on the new one would leave the caller believing both are understood.
     */
    public function testTheOldTypesNameIsRejectedEvenWhenTypeIsAlsoGiven(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'types' => 'tickets']);
    }

    /**
     * An empty value means the parameter was never really sent — `?types=` and
     * `?types[]=` are what a query string leaves behind, not a request for the
     * old behaviour.
     */
    #[DataProvider('emptyTypesValues')]
    public function testAnEmptyOldTypesValueIsTreatedAsAbsent(mixed $types): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'types' => $types]);

        $this->assertSame('tickets', $parameters->type);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyTypesValues(): array
    {
        return [
            'empty string' => [''],
            'empty list' => [[]],
            'list of one empty string' => [['']],
        ];
    }

    /**
     * The parameters are echoed back so a client can see the page it got, and so
     * `start` for the next request is obvious from the response it holds.
     */
    public function testTheEffectiveParametersAreEchoedBack(): void
    {
        $parameters = DeletedRequestParameters::fromInput([
            'type' => 'projects',
            'start' => 82,
            'limit' => 10,
            'deletedAfter' => 1759906882,
        ]);

        $this->assertSame([
            'type' => 'projects',
            'start' => 82,
            'limit' => 10,
            'deletedAfter' => 1759906882,
        ], $parameters->toArray());
    }
}
