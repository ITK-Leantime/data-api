<?php

namespace Leantime\Plugins\APIData\Tests\Service;

use Carbon\CarbonInterface;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Plugins\APIData\Repositories\ApiDataRepository;
use Leantime\Plugins\APIData\Repositories\SchemaRepository;
use Leantime\Plugins\APIData\Services\APIData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(APIData::class)]
final class APIDataTest extends TestCase
{
    /**
     * The reported incident: a timesheet whose user has been deleted from
     * Leantime. `user.username` arrives null from the left join, and the whole
     * response used to fail with a TypeError rather than that one field being
     * null.
     */
    public function testGetTimesheetsMapsAMissingUserToNullAndKeepsTheUserId(): void
    {
        $service = $this->makeServiceReturningTimesheets([
            $this->timesheetRow(['username' => null, 'userId' => 57]),
        ]);

        $timesheets = $service->getTimesheets(0, 100);

        $this->assertCount(1, $timesheets);
        $this->assertNull($timesheets[0]->username);
        $this->assertSame(57, $timesheets[0]->userId);
        $this->assertSame(7.5, $timesheets[0]->hours);
    }

    /**
     * Guards the constructor argument order. `$kind` moved ahead of the two
     * optional date parameters, and CarbonImmutable implements __toString(), so
     * a mis-ordered date would land in `$kind` as a coerced string instead of
     * raising a TypeError. Only asserting each field individually catches that.
     */
    public function testGetTimesheetsMapsEveryFieldToItsOwnProperty(): void
    {
        $service = $this->makeServiceReturningTimesheets([$this->timesheetRow()]);

        $timesheet = $service->getTimesheets(0, 100)[0];

        $this->assertSame(4711, $timesheet->id);
        $this->assertSame(12, $timesheet->ticketId);
        $this->assertSame(92, $timesheet->projectId);
        $this->assertSame('Worked on stuff', $timesheet->description);
        $this->assertSame(7.5, $timesheet->hours);
        $this->assertSame(57, $timesheet->userId);
        $this->assertSame('anne@aarhus.dk', $timesheet->username);
        $this->assertSame('GENERAL_BILLABLE', $timesheet->kind);

        $this->assertInstanceOf(CarbonInterface::class, $timesheet->workDate);
        $this->assertSame('2026-03-02 09:00:00', $timesheet->workDate->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $timesheet->workDate->timezoneName);

        $this->assertInstanceOf(CarbonInterface::class, $timesheet->modified);
        $this->assertSame('2026-03-03 11:30:00', $timesheet->modified->format('Y-m-d H:i:s'));
    }

    /**
     * Leantime stores "no date" as the zero date rather than null. Carbon throws
     * on a format mismatch, so this guard has to stay in place.
     */
    public function testGetTimesheetsTreatsTheZeroDateSentinelAsNull(): void
    {
        $service = $this->makeServiceReturningTimesheets([
            $this->timesheetRow(['workDate' => '0000-00-00 00:00:00', 'modified' => null]),
        ]);

        $timesheet = $service->getTimesheets(0, 100)[0];

        $this->assertNull($timesheet->workDate);
        $this->assertNull($timesheet->modified);
    }

    /**
     * Leantime's `getStateLabels()` falls back to `session('currentProject')`
     * when it is handed a null project id, so asking it for labels would return
     * some unrelated project's status list and put a wrong status on the ticket.
     * A ticket without a project has no status to resolve.
     */
    public function testGetTicketsDoesNotLookUpStatusLabelsForATicketWithoutAProject(): void
    {
        $ticketRepository = $this->createMock(TicketRepository::class);
        $ticketRepository->expects($this->never())->method('getStateLabels');

        $repository = $this->createMock(ApiDataRepository::class);
        $repository->method('getTickets')->willReturn([$this->ticketRow(['projectId' => null])]);

        $tickets = $this->makeService($repository, $ticketRepository)->getTickets(0, 100);

        $this->assertNull($tickets[0]->projectId);
        $this->assertNull($tickets[0]->status);
    }

    /**
     * The normal path still has to resolve the status against the ticket's own
     * project.
     */
    public function testGetTicketsResolvesStatusFromTheProjectsOwnLabels(): void
    {
        $ticketRepository = $this->createMock(TicketRepository::class);
        $ticketRepository->expects($this->once())
            ->method('getStateLabels')
            ->with(92)
            ->willReturn($this->stateLabels());

        $repository = $this->createMock(ApiDataRepository::class);
        $repository->method('getTickets')->willReturn([
            $this->ticketRow(['projectId' => 92, 'status' => 3]),
        ]);

        $tickets = $this->makeService($repository, $ticketRepository)->getTickets(0, 100);

        $this->assertSame(92, $tickets[0]->projectId);
        $this->assertSame('NEW', $tickets[0]->status);
    }

    /**
     * Users are the fourth entity type to carry a sync watermark. It comes from
     * the plugin's own column, so it has to survive the mapping as UTC.
     */
    public function testGetWorkersMapsTheModifiedTimestampAsUtc(): void
    {
        $service = $this->makeServiceReturningWorkers([$this->workerRow()]);

        $worker = $service->getWorkers(0, 100)[0];

        $this->assertSame(57, $worker->id);
        $this->assertSame('anne@aarhus.dk', $worker->email);
        $this->assertSame('Anne Andersen', $worker->name);

        $this->assertInstanceOf(CarbonInterface::class, $worker->modified);
        $this->assertSame('2026-03-03 11:30:00', $worker->modified->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $worker->modified->timezoneName);
    }

    /**
     * `CONCAT(firstname, ' ', lastname)` is NULL when either column is, and one
     * such user used to fail the whole /users response with a TypeError.
     */
    public function testGetWorkersMapsAUserWithoutANameToNull(): void
    {
        $service = $this->makeServiceReturningWorkers([
            $this->workerRow(['name' => null, 'modified' => '0000-00-00 00:00:00']),
        ]);

        $worker = $service->getWorkers(0, 100)[0];

        $this->assertNull($worker->name);
        $this->assertNull($worker->modified);
    }

    /**
     * @param list<object> $rows
     */
    private function makeServiceReturningTimesheets(array $rows): APIData
    {
        $repository = $this->createMock(ApiDataRepository::class);
        $repository->method('getTimesheets')->willReturn($rows);

        return $this->makeService($repository);
    }

    /**
     * @param list<object> $rows
     */
    private function makeServiceReturningWorkers(array $rows): APIData
    {
        $repository = $this->createMock(ApiDataRepository::class);
        $repository->method('getWorkers')->willReturn($rows);

        return $this->makeService($repository);
    }

    private function makeService(ApiDataRepository $repository, ?TicketRepository $ticketRepository = null): APIData
    {
        return new APIData(
            $ticketRepository ?? $this->createMock(TicketRepository::class),
            $repository,
            $this->createMock(SchemaRepository::class),
        );
    }

    /**
     * A row as the repository returns it — the select list in
     * `ApiDataRepository::getTimesheets()` decides these keys.
     *
     * @param array<string, mixed> $overrides
     */
    private function timesheetRow(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 4711,
            'ticketId' => 12,
            'projectId' => 92,
            'description' => 'Worked on stuff',
            'hours' => 7.5,
            'userId' => 57,
            'username' => 'anne@aarhus.dk',
            'kind' => 'GENERAL_BILLABLE',
            'workDate' => '2026-03-02 09:00:00',
            'modified' => '2026-03-03 11:30:00',
        ], $overrides);
    }

    /**
     * A row as `ApiDataRepository::getTickets()` returns it.
     *
     * @param array<string, mixed> $overrides
     */
    private function ticketRow(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 4711,
            'projectId' => 92,
            'headline' => 'Fix the thing',
            'status' => 3,
            'milestoneid' => 0,
            'tags' => '',
            'username' => 'anne@aarhus.dk',
            'planHours' => null,
            'hourRemaining' => null,
            'dateToFinish' => null,
            'editTo' => null,
            'modified' => null,
        ], $overrides);
    }

    /**
     * A row as `ApiDataRepository::getWorkers()` returns it — `modified` is the
     * aliased `itk_data_api_modified` column.
     *
     * @param array<string, mixed> $overrides
     */
    private function workerRow(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 57,
            'username' => 'anne@aarhus.dk',
            'name' => 'Anne Andersen',
            'modified' => '2026-03-03 11:30:00',
        ], $overrides);
    }

    /**
     * Mirrors the shape of Leantime v3.9.7's `$statusListSeed`: keyed by the
     * integer status id, each entry carrying a `statusType`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stateLabels(): array
    {
        return [
            3 => [
                'name' => 'status.new',
                'class' => 'label-info',
                'statusType' => 'NEW',
                'kanbanCol' => true,
                'sortKey' => 1,
            ],
        ];
    }
}
