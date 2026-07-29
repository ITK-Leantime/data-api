<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Leantime\Plugins\APIData\Model\TimesheetData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimesheetData::class)]
final class TimesheetDataTest extends TestCase
{
    /**
     * A timesheet keeps its hours when the user who logged them is deleted from
     * Leantime. `user.username` comes from a left join, so it is null whenever
     * that join finds no row.
     */
    public function testAcceptsNullUsernameWhenTheUserWasDeleted(): void
    {
        $timesheet = $this->makeTimesheet(['username' => null]);

        $this->assertNull($timesheet->username);
        $this->assertSame(7.5, $timesheet->hours);
    }

    /**
     * `ticket.projectId` comes from the other left join, so a timesheet whose
     * ticket was deleted has neither a ticket id nor a project id.
     */
    public function testAcceptsNullTicketIdAndProjectIdWhenTheTicketWasDeleted(): void
    {
        $timesheet = $this->makeTimesheet(['ticketId' => null, 'projectId' => null]);

        $this->assertNull($timesheet->ticketId);
        $this->assertNull($timesheet->projectId);
    }

    public function testAcceptsNullKind(): void
    {
        $timesheet = $this->makeTimesheet(['kind' => null]);

        $this->assertNull($timesheet->kind);
    }

    /**
     * Hours logged by a deleted user stay attributable through the user id, so
     * a consumer can group them per departed worker instead of collapsing them
     * into one anonymous pile.
     */
    public function testExposesUserIdSoOrphanedHoursStayAttributable(): void
    {
        $timesheet = $this->makeTimesheet(['userId' => 57, 'username' => null]);

        $this->assertSame(57, $timesheet->userId);
        $this->assertNull($timesheet->username);
    }

    /**
     * `$kind` has to move ahead of the optional date parameters, since a
     * required parameter after an optional one is deprecated. CarbonImmutable
     * implements __toString() and this codebase does not use strict_types, so a
     * date landing in a string parameter would be coerced silently instead of
     * raising a TypeError. Pin the declarations so a swap cannot go unnoticed.
     */
    public function testKindHoldsAStatusStringAndDatesHoldCarbonInstances(): void
    {
        $timesheet = $this->makeTimesheet([
            'kind' => 'GENERAL_BILLABLE',
            'workDate' => CarbonImmutable::parse('2026-03-02 09:00:00'),
        ]);

        $this->assertSame('GENERAL_BILLABLE', $timesheet->kind);
        $this->assertInstanceOf(CarbonInterface::class, $timesheet->workDate);
        $this->assertSame('2026-03-02 09:00:00', $timesheet->workDate->format('Y-m-d H:i:s'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeTimesheet(array $overrides = []): TimesheetData
    {
        return new TimesheetData(...[
            'id' => 1,
            'ticketId' => 2,
            'projectId' => 3,
            'description' => 'Worked on stuff',
            'hours' => 7.5,
            'userId' => 57,
            'username' => 'anne@aarhus.dk',
            'kind' => 'GENERAL_BILLABLE',
            'workDate' => null,
            'modified' => null,
            ...$overrides,
        ]);
    }
}
