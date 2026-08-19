<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\BadRequestException;
use Leantime\Plugins\APIData\Model\DeletedRequestParameters;
use PHPUnit\Framework\Attributes\CoversClass;
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
     * The endpoint took a `types` list before it was paginated. A caller still
     * sending one must be told, not quietly served whichever type came first.
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
        $this->assertNull($parameters->deleted);
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

    public function testTheDeletedTimestampIsNarrowedToAnInteger(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'deleted' => '1759906882']);

        $this->assertSame(1759906882, $parameters->deleted);
    }

    public function testANonNumericDeletedTimestampIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('deleted must be a whole number.');

        DeletedRequestParameters::fromInput(['type' => 'tickets', 'deleted' => 'last week']);
    }

    public function testAnEmptyDeletedTimestampIsTreatedAsAbsent(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['type' => 'tickets', 'deleted' => '']);

        $this->assertNull($parameters->deleted);
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
            'deleted' => 1759906882,
        ]);

        $this->assertSame([
            'type' => 'projects',
            'start' => 82,
            'limit' => 10,
            'deleted' => 1759906882,
        ], $parameters->toArray());
    }
}
