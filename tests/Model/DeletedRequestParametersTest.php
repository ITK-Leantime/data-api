<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\DeletedRequestParameters;
use Leantime\Plugins\APIData\Model\InvalidRequestException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeletedRequestParameters::class)]
final class DeletedRequestParametersTest extends TestCase
{
    /**
     * `types` used to be read without a fallback, so omitting it was an undefined
     * key followed by a foreach over null.
     */
    public function testOmittingTypesFallsBackToEverySupportedType(): void
    {
        $parameters = DeletedRequestParameters::fromInput([]);

        $this->assertSame(DeletedRequestParameters::supportedTypes(), $parameters->types);
        $this->assertNull($parameters->deleted);
    }

    public function testACommaSeparatedListIsAcceptedForTypes(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['types' => 'tickets, timesheets']);

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
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('types must only contain: projects, milestones, tickets, timesheets.');

        DeletedRequestParameters::fromInput(['types' => '<script>']);
    }

    /**
     * `users` has no deleted-entities table, so it is not a valid type here even
     * though the entity endpoints support it.
     */
    public function testUsersIsNotASupportedDeletedType(): void
    {
        $this->expectException(InvalidRequestException::class);

        DeletedRequestParameters::fromInput(['types' => 'users']);
    }

    public function testTheDeletedTimestampIsNarrowedToAnInteger(): void
    {
        $parameters = DeletedRequestParameters::fromInput(['deleted' => '1759906882']);

        $this->assertSame(1759906882, $parameters->deleted);
    }

    public function testANonNumericDeletedTimestampIsRejected(): void
    {
        $this->expectException(InvalidRequestException::class);

        DeletedRequestParameters::fromInput(['deleted' => 'last week']);
    }
}
