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
     * The endpoint has no limit, so every type returns its whole deletion
     * history. Omitting types used to be an undefined key followed by a foreach
     * over null; it must not become a bare request that scans every table.
     */
    public function testOmittingTypesIsRejectedRatherThanScanningEveryTable(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('types is required and must contain at least one of: projects, milestones, tickets, timesheets.');

        DeletedRequestParameters::fromInput([]);
    }

    /**
     * An empty list would answer 200 with nothing, which a sync client reads as
     * "nothing was deleted" and acts on.
     */
    public function testAnEmptyTypesListIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['types' => []]);
    }

    public function testAnEmptyTypesStringIsRejected(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['types' => '']);
    }

    public function testACommaSeparatedListIsAcceptedForTypes(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => 'tickets, timesheets']);

        $this->assertSame(['tickets', 'timesheets'], $parameters->types);
    }

    /**
     * The comma separated form is trimmed, and types are matched strictly, so an
     * untrimmed `?types[]=tickets%20` used to be a 400.
     */
    public function testWhitespaceAroundArrayElementsIsIgnored(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => [' tickets ', 'timesheets']]);

        $this->assertSame(['tickets', 'timesheets'], $parameters->types);
    }

    public function testDuplicateTypesAreCollapsed(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => ['tickets', 'tickets']]);

        $this->assertSame(['tickets'], $parameters->types);
    }

    /**
     * An unknown type used to reach the repository's match arm, which threw with
     * the raw value in the message and rendered it on the error page.
     */
    public function testAnUnknownTypeIsRejectedWithoutEchoingTheInput(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('types must only contain: projects, milestones, tickets, timesheets.');

        DeletedRequestParameters::fromInput(['types' => '<script>']);
    }

    /**
     * `users` has no deleted-entities table, so it is not a valid type here even
     * though the entity endpoints support it.
     */
    public function testUsersIsNotASupportedDeletedType(): void
    {
        $this->expectException(BadRequestException::class);

        DeletedRequestParameters::fromInput(['types' => 'users']);
    }

    public function testTheDeletedTimestampIsNarrowedToAnInteger(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => 'tickets', 'deleted' => '1759906882']);

        $this->assertSame(1759906882, $parameters->deleted);
    }

    public function testANonNumericDeletedTimestampIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('deleted must be a whole number.');

        DeletedRequestParameters::fromInput(['types' => 'tickets', 'deleted' => 'last week']);
    }

    public function testAnEmptyDeletedTimestampIsTreatedAsAbsent(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => 'tickets', 'deleted' => '']);

        $this->assertNull($parameters->deleted);
    }
}
