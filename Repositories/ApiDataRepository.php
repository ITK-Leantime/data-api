<?php

namespace Leantime\Plugins\APIData\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
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

    public function getTimesheets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        return $this->query()
            ->from("zp_timesheets", "timesheet")
            ->select(["timesheet.id", "timesheet.description", "timesheet.hours", "timesheet.workDate", "timesheet.modified", "timesheet.ticketId", "timesheet.kind", "user.username", "ticket.projectId"])
            ->where("timesheet.id", ">=", $startId)
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
}
