<?php

namespace Leantime\Plugins\APIData\Services;

use Carbon\CarbonImmutable;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Plugins\APIData\Model\DeletedData;
use Leantime\Plugins\APIData\Model\MilestoneData;
use Leantime\Plugins\APIData\Model\ProjectData;
use Leantime\Plugins\APIData\Model\TicketData;
use Leantime\Plugins\APIData\Model\TimesheetData;
use Leantime\Plugins\APIData\Model\WorkerData;
use Leantime\Plugins\APIData\Repositories\ApiDataRepository;

class APIData
{
    public const TYPE_PROJECTS = 'projects';
    public const TYPE_MILESTONES = 'milestones';
    public const TYPE_TICKETS = 'tickets';
    public const TYPE_TIMESHEETS = 'timesheets';
    public const TYPE_WORKERS = 'users';
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly ApiDataRepository $apiDataRepository,
    ) {
    }

    public function install(): void
    {
        $this->apiDataRepository->setupTables();
    }

    public function uninstall(): void
    {
        // The tables are intentionally left in place to preserve data through
        // install/uninstall cycles; only the triggers are removed.
        $this->apiDataRepository->removeTriggers();
    }

    /**
     * Fetch projects mapped to ProjectData models.
     *
     * @param int                         $startId       Lowest project id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of project ids to filter by.
     *
     * @return array<int, ProjectData>
     */
    public function getProjects(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        $values = $this->apiDataRepository->getProjects($startId, $limit, $modifiedAfter, $ids);

        return array_map(function ($value) {
            return new ProjectData(
                $value->id,
                $value->name,
                $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    /**
     * Fetch milestones mapped to MilestoneData models.
     *
     * @param int                         $startId       Lowest ticket id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of ticket ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, MilestoneData>
     */
    public function getMilestones(int $startId, int $limit, int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $values = $this->apiDataRepository->getMilestones($startId, $limit, $modifiedAfter, $ids, $projectIds);

        return array_map(function ($value) {
            return new MilestoneData(
                $value->id,
                $value->projectId,
                $value->headline,
                $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    /**
     * Fetch tickets mapped to TicketData models.
     *
     * @param int                         $startId       Lowest ticket id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of ticket ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, TicketData>
     */
    public function getTickets(int $startId, int $limit, int $modifiedAfter = null, array $ids = null, ?array $projectIds = null): array
    {
        $values = $this->apiDataRepository->getTickets($startId, $limit, $modifiedAfter, $ids, $projectIds);

        return array_map(function ($value) {
            $projectStatuses = $this->ticketRepository->getStateLabels($value->projectId);

            return new TicketData(
                $value->id,
                $value->projectId,
                $value->headline,
                $projectStatuses[$value->status]['statusType'] ?? null,
                $this->getMilestoneId($value),
                !empty($value->tags) ? explode(",", $value->tags) : [],
                $value->username,
                $value->planHours,
                $value->hourRemaining,
                $this->getCarbonFromDatabaseValue($value->dateToFinish),
                $this->getCarbonFromDatabaseValue($value->editTo),
                $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    /**
     * Fetch timesheets mapped to TimesheetData models.
     *
     * @param int                         $startId       Lowest timesheet id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of timesheet ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, TimesheetData>
     */
    public function getTimesheets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $values = $this->apiDataRepository->getTimesheets($startId, $limit, $modifiedAfter, $ids, $projectIds);

        return array_map(function ($value) {
            return new TimesheetData(
                $value->id,
                $value->ticketId,
                $value->projectId,
                $value->description,
                $value->hours,
                $value->username,
                $value->kind,
                $this->getCarbonFromDatabaseValue($value->workDate),
                $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    /**
     * Fetch workers mapped to WorkerData models.
     *
     * @param int                         $startId       Lowest user id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of user ids to filter by.
     * @param array<int, int|string>|null $projectIds    Accepted for interface symmetry; workers are not project-scoped.
     *
     * @return array<int, WorkerData>
     */
    public function getWorkers(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $values = $this->apiDataRepository->getWorkers($startId, $limit, $modifiedAfter, $ids);

        return array_map(function ($value) {
            return new WorkerData(
                $value->id,
                $value->username,
                $value->name,
            );
        }, $values);
    }

    /**
     * Fetch deleted-entity entries mapped to DeletedData models.
     *
     * @param string   $type         One of the APIData::TYPE_* constants.
     * @param int|null $deletedAfter Optional unix timestamp lower bound.
     *
     * @return array<int, DeletedData>
     */
    public function getDeleted(string $type, ?int $deletedAfter = null): array
    {
        $values = $this->apiDataRepository->getDeleted($type, $deletedAfter);

        return array_map(fn ($entry) => new DeletedData(
            $entry->entryId,
            $this->getCarbonFromDatabaseValue($entry->dateDeleted),
        ), $values);
    }

    /**
     * Parse a database datetime value into a CarbonImmutable, or null.
     *
     * @param mixed $value Raw database value (datetime string or null).
     *
     * @return CarbonImmutable|null
     */
    private function getCarbonFromDatabaseValue(mixed $value): ?CarbonImmutable
    {
        // "0000-00-00 00:00:00" equals null.
        return $value !== null && $value !== "0000-00-00 00:00:00"
            ? CarbonImmutable::createFromFormat(APIData::DATE_FORMAT, (string) $value, 'UTC')
            : null;
    }

    /**
     * Resolve a ticket's milestone id, treating 0 as null.
     *
     * @param mixed $value Row containing a milestoneid property.
     *
     * @return int|null
     */
    private function getMilestoneId(mixed $value): ?int
    {
        // milestoneid=0 equals null.
        return $value->milestoneid !== null && $value->milestoneid > 0 ? (int) $value->milestoneid : null;
    }
}
