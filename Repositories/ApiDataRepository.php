<?php

namespace Leantime\Plugins\APIData\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Leantime\Plugins\APIData\Services\APIData;

class ApiDataRepository
{
    private function query(): Builder
    {
        return app('db')->connection()->query();
    }

    public function getProjects(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        return $this->query()
            ->select(["project.id", "project.name", $this->modifiedSelect("project")])
            ->from("zp_projects", "project")
            ->where("project.id", ">=", $startId)
            ->when($modifiedAfter !== null, fn ($query) => $query->where($this->modified("project"), ">=", $this->cutoff($modifiedAfter)))
            ->when($ids !== null, fn ($query) => $query->whereIn("project.id", $ids))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getMilestones(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->select(["ticket.id", "ticket.headline", "ticket.projectId", $this->modifiedSelect("ticket")])
            ->from("zp_tickets", "ticket")
            ->where("ticket.id", ">=", $startId)
            ->where("ticket.type", "=", "milestone")
            ->when($modifiedAfter !== null, fn ($query) => $query->where($this->modified("ticket"), ">=", $this->cutoff($modifiedAfter)))
            ->when($ids !== null, fn ($query) => $query->whereIn("ticket.id", $ids))
            ->when($projectIds !== null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTickets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->select(["ticket.id", "ticket.headline", "ticket.projectId", "ticket.status", "ticket.planHours", "ticket.hourRemaining", "ticket.tags", "ticket.dateToFinish", "ticket.editTo", "ticket.milestoneid", $this->modifiedSelect("ticket"), "user.username"])
            ->from("zp_tickets", "ticket")
            ->where("ticket.id", ">=", $startId)
            ->where("ticket.type", "<>", "milestone")
            ->leftJoin('zp_user as user', "user.id", "=", "ticket.editorId")
            ->when($modifiedAfter !== null, fn ($query) => $query->where($this->modified("ticket"), ">=", $this->cutoff($modifiedAfter)))
            ->when($ids !== null, fn ($query) => $query->whereIn("ticket.id", $ids))
            ->when($projectIds !== null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getTimesheets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->from("zp_timesheets", "timesheet")
            ->select(["timesheet.id", "timesheet.description", "timesheet.hours", "timesheet.workDate", $this->modifiedSelect("timesheet"), "timesheet.ticketId", "timesheet.userId", "timesheet.kind", "user.username", "ticket.projectId"])
            ->where("timesheet.id", ">=", $startId)
            ->whereNotNull("timesheet.hours")
            ->leftJoin('zp_user as user', "user.id", "=", "timesheet.userId")
            ->leftJoin('zp_tickets as ticket', "ticket.id", "=", "timesheet.ticketId")
            ->when($modifiedAfter !== null, fn ($query) => $query->where($this->modified("timesheet"), ">=", $this->cutoff($modifiedAfter)))
            ->when($ids !== null, fn ($query) => $query->whereIn("timesheet.id", $ids))
            ->when($projectIds !== null, fn ($query) => $query->whereIn("ticket.projectId", $projectIds))
            ->orderBy("timesheet.id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getWorkers(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        return $this->query()
            ->from("zp_user", "worker")
            // CONCAT_WS skips a missing name part, so a worker with only a
            // firstname keeps a usable name. NULLIF turns an all-blank name into
            // null rather than a string of whitespace.
            ->select(["worker.id", "worker.username", DB::raw("NULLIF(TRIM(CONCAT_WS(' ', worker.firstname, worker.lastname)), '') as name"), $this->modifiedSelect("worker")])
            ->where("worker.id", ">=", $startId)
            ->where("worker.source", "<>", "api")
            ->when($modifiedAfter !== null, fn ($query) => $query->where($this->modified("worker"), ">=", $this->cutoff($modifiedAfter)))
            ->when($ids !== null, fn ($query) => $query->whereIn("worker.id", $ids))
            ->orderBy("worker.id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Paged on the tracking table's own row id rather than on `entryId`: rows are
     * appended as entities are deleted, so `id` is the only column that both
     * orders them and stays put while a client pages through.
     */
    public function getDeleted(string $type, int $startId, int $limit, ?int $deletedAfter = null): array
    {
        $tableName = match ($type) {
            APIData::TYPE_PROJECTS => 'itk_projects_deleted',
            APIData::TYPE_TICKETS, APIData::TYPE_MILESTONES => 'itk_tickets_deleted',
            APIData::TYPE_TIMESHEETS => 'itk_timesheets_deleted',
            default => throw new \Exception("Invalid type $type"),
        };

        return $this->query()
            ->from($tableName, "entry")
            ->select(["entry.id", "entry.entryId", "entry.dateDeleted"])
            ->where("entry.id", ">=", $startId)
            ->when($type === APIData::TYPE_MILESTONES, fn ($query) => $query->where('type', '=', 'milestone'))
            ->when($type === APIData::TYPE_TICKETS, fn ($query) => $query->where('type', '<>', 'milestone'))
            ->when($deletedAfter !== null, fn ($query) => $query->where("entry.dateDeleted", ">=", $this->cutoff($deletedAfter)))
            ->orderBy("entry.id", "ASC")
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * The plugin-owned timestamp column, qualified by the query's table alias.
     * Core's own `modified` is not maintained on every write path, so it cannot
     * carry the modifiedAfter contract — see SchemaRepository.
     */
    private function modified(string $alias): string
    {
        return sprintf('%s.%s', $alias, SchemaRepository::COLUMN);
    }

    /**
     * Exposed to consumers as plain `modified`, so the column swap is invisible
     * to them and to the mapping in APIData.
     */
    private function modifiedSelect(string $alias): string
    {
        return sprintf('%s as modified', $this->modified($alias));
    }

    private function cutoff(int $timestamp): string
    {
        // Explicit UTC: Carbon 3 defaults to it, but Carbon comes from the host
        // Leantime install, and the triggers write UTC_TIMESTAMP().
        return CarbonImmutable::createFromTimestamp($timestamp, 'UTC')->format(APIData::DATE_FORMAT);
    }
}
