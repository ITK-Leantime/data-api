<?php

namespace Leantime\Plugins\APIData\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Leantime\Plugins\APIData\Services\APIData;

/**
 * Data access for the APIData plugin's export queries and deleted-entity tracking.
 */
class ApiDataRepository
{
    /**
     * Build a fresh query builder on the default connection.
     *
     * @return Builder
     */
    private function query(): Builder
    {
        return app('db')->connection()->query();
    }

    /**
     * Fetch projects.
     *
     * @param int                         $startId       Lowest project id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of project ids to filter by.
     *
     * @return array<int, \stdClass>
     */
    public function getProjects(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        return $this->query()
            ->select(["id", "name", "modified"])
            ->from("zp_projects", "project")
            ->where("project.id", ">=", $startId)
            ->when($modifiedAfter !== null, fn ($query) => $query->where("project.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT)))
            ->when($ids !== null, fn ($query) => $query->whereIn("project.id", $ids))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Fetch milestones (tickets of type "milestone").
     *
     * @param int                         $startId       Lowest ticket id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of ticket ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, \stdClass>
     */
    public function getMilestones(int $startId, int $limit, int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->select(["id", "headline", "projectId", "modified"])
            ->from("zp_tickets", "ticket")
            ->where("ticket.id", ">=", $startId)
            ->where("ticket.type", "=", "milestone")
            ->when($modifiedAfter !== null, fn ($query) => $query->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT)))
            ->when($ids !== null, fn ($query) => $query->whereIn("ticket.id", $ids))
            ->when($projectIds != null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Fetch tickets (excluding milestones).
     *
     * @param int                         $startId       Lowest ticket id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of ticket ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, \stdClass>
     */
    public function getTickets(int $startId, int $limit, int $modifiedAfter = null, array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->select(["ticket.id", "ticket.headline", "ticket.projectId", "ticket.status", "ticket.planHours", "ticket.hourRemaining", "ticket.tags", "ticket.dateToFinish", "ticket.editTo", "ticket.milestoneid", "ticket.modified", "user.username"])
            ->from("zp_tickets", "ticket")
            ->where("ticket.id", ">=", $startId)
            ->where("ticket.type", "<>", "milestone")
            ->leftJoin('zp_user as user', "user.id", "=", "ticket.editorId")
            ->when($modifiedAfter !== null, fn ($query) => $query->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT)))
            ->when($ids !== null, fn ($query) => $query->whereIn("ticket.id", $ids))
            ->when($projectIds != null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Fetch timesheets.
     *
     * @param int                         $startId       Lowest timesheet id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of timesheet ids to filter by.
     * @param array<int, int|string>|null $projectIds    Optional list of project ids to filter by.
     *
     * @return array<int, \stdClass>
     */
    public function getTimesheets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->from("zp_timesheets", "timesheet")
            ->select(["timesheet.id", "timesheet.description", "timesheet.hours", "timesheet.workDate", "timesheet.modified", "timesheet.ticketId", "timesheet.kind", "user.username", "ticket.projectId"])
            ->where("timesheet.id", ">=", $startId)
            ->whereNotNull("timesheet.hours")
            ->leftJoin('zp_user as user', "user.id", "=", "timesheet.userId")
            ->leftJoin('zp_tickets as ticket', "ticket.id", "=", "timesheet.ticketId")
            ->when($modifiedAfter !== null, fn ($query) => $query->where("timesheet.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT)))
            ->when($ids !== null, fn ($query) => $query->whereIn("timesheet.id", $ids))
            ->when($projectIds != null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("timesheet.id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Fetch workers (non-API users).
     *
     * @param int                         $startId       Lowest user id to include.
     * @param int                         $limit         Maximum number of rows to return.
     * @param int|null                    $modifiedAfter Optional unix timestamp lower bound.
     * @param array<int, int|string>|null $ids           Optional list of user ids to filter by.
     *
     * @return array<int, \stdClass>
     */
    public function getWorkers(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        return $this->query()
            ->from("zp_user", "worker")
            ->select(["worker.id", "worker.username", DB::raw("CONCAT(worker.firstname, ' ', worker.lastname) as name")])
            ->where("worker.id", ">=", $startId)
            ->where("worker.source", "<>", "api")
            ->when($modifiedAfter !== null, fn ($query) => $query->where("worker.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT)))
            ->when($ids !== null, fn ($query) => $query->whereIn("worker.id", $ids))
            ->orderBy("worker.id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Fetch deleted-entity tracking entries for a given type.
     *
     * @param string   $type         One of the APIData::TYPE_* constants.
     * @param int|null $deletedAfter Optional unix timestamp lower bound.
     *
     * @return array<int, \stdClass>
     */
    public function getDeleted(string $type, ?int $deletedAfter = null): array
    {
        $tableName = match ($type) {
            APIData::TYPE_PROJECTS => 'itk_projects_deleted',
            APIData::TYPE_TICKETS, APIData::TYPE_MILESTONES => 'itk_tickets_deleted',
            APIData::TYPE_TIMESHEETS => 'itk_timesheets_deleted',
            default => throw new \Exception("Invalid type $type"),
        };

        return $this->query()
            ->from($tableName, "entry")
            ->select(["entryId", "dateDeleted"])
            ->when($type === APIData::TYPE_MILESTONES, fn ($query) => $query->where('type', '=', 'milestone'))
            ->when($type === APIData::TYPE_TICKETS, fn ($query) => $query->where('type', '<>', 'milestone'))
            ->when($deletedAfter !== null, fn ($query) => $query->where("entry.dateDeleted", ">=", CarbonImmutable::createFromTimestamp($deletedAfter)->format(APIData::DATE_FORMAT)))
            ->get()
            ->toArray();
    }

    /**
     * Create the deleted-entity tracking tables and their triggers.
     *
     * Uses unprepared() because this is multi-statement DDL: the CREATE TRIGGER
     * bodies contain their own statement terminators, which a prepared statement
     * cannot handle.
     *
     * @return void
     */
    public function setupTables(): void
    {
        app('db')->connection()->unprepared(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `itk_projects_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `itk_tickets_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `type` varchar(255) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `itk_timesheets_deleted` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `entryId` int(11) DEFAULT NULL,
                `dateDeleted` datetime DEFAULT NOW(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TRIGGER itk_projects_deleted_trigger
            AFTER DELETE ON zp_projects
            FOR EACH ROW
            BEGIN
               INSERT INTO itk_projects_deleted(entryId)
               VALUES (OLD.id);
            END;

            CREATE TRIGGER itk_tickets_deleted_trigger
            AFTER DELETE ON zp_tickets
            FOR EACH ROW
            BEGIN
               INSERT INTO itk_tickets_deleted(entryId, type)
               VALUES (OLD.id, OLD.type);
            END;

            CREATE TRIGGER itk_timesheets_deleted_trigger
            AFTER DELETE ON zp_timesheets
            FOR EACH ROW
            BEGIN
               INSERT INTO itk_timesheets_deleted(entryId)
               VALUES (OLD.id);
            END;
        SQL);
    }

    /**
     * Drop the deleted-entity tracking triggers.
     *
     * The tables are intentionally left in place so their data survives an
     * install/uninstall cycle.
     *
     * @return void
     */
    public function removeTriggers(): void
    {
        app('db')->connection()->unprepared(<<<'SQL'
            DROP TRIGGER itk_projects_deleted_trigger;
            DROP TRIGGER itk_tickets_deleted_trigger;
            DROP TRIGGER itk_timesheets_deleted_trigger;
        SQL);
    }
}
