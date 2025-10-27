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
        $qb = $this->query();

        $qb->select(["id", "name", "modified"]);
        $qb->from("zp_projects", "project");
        $qb->where("project.id", ">=", $startId);

        if ($modifiedAfter !== null) {
            $qb->where("project.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("project.id", $ids);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        return $qb->get()->toArray();
    }

    public function getMilestones(int $startId, int $limit, int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $qb = $this->query();

        $qb->select(["id", "headline", "projectId", "modified"]);
        $qb->from("zp_tickets", "ticket");
        $qb->where("ticket.id", ">=", $startId);
        $qb->where("ticket.type", "=", "milestone");

        if ($modifiedAfter !== null) {
            $qb->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("ticket.id", $ids);
        }

        if ($projectIds !== null) {
            $qb->whereIn("ticket.projectId", $projectIds);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        return $qb->get()->toArray();
    }

    public function getTickets(int $startId, int $limit, int $modifiedAfter = null, array $ids = null, ?array $projectIds = null): array
    {
        $qb = $this->query();

        $qb->select(["ticket.id", "ticket.headline", "ticket.projectId", "ticket.status", "ticket.planHours", "ticket.hourRemaining", "ticket.tags", "ticket.dateToFinish", "ticket.editTo", "ticket.milestoneid", "ticket.modified", "user.username"]);
        $qb->from("zp_tickets", "ticket");
        $qb->where("ticket.id", ">=", $startId);
        $qb->where("ticket.type", "<>", "milestone");
        $qb->leftJoin('zp_user as user', "user.id", "=", "ticket.editorId");

        if ($modifiedAfter !== null) {
            $qb->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("ticket.id", $ids);
        }

        if ($projectIds !== null) {
            $qb->whereIn("ticket.projectId", $projectIds);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        return $qb->get()->toArray();
    }

    public function getTimesheets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $qb = $this->query();

        $qb->from("zp_timesheets", "timesheet");
        $qb->select(["timesheet.id", "timesheet.description", "timesheet.hours", "timesheet.workDate", "timesheet.modified", "timesheet.ticketId", "timesheet.kind", "user.username", "ticket.projectId"]);
        $qb->where("timesheet.id", ">=", $startId);
        $qb->leftJoin('zp_user as user', "user.id", "=", "timesheet.userId");
        $qb->leftJoin('zp_tickets as ticket', "ticket.id", "=", "timesheet.ticketId");

        if ($modifiedAfter !== null) {
            $qb->where("timesheet.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedAfter)->format(APIData::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("timesheet.id", $ids);
        }

        if ($projectIds !== null) {
            $qb->whereIn('ticket.projectId', $projectIds);
        }

        $qb->orderBy("timesheet.id", "ASC");
        $qb->limit($limit);

        return $qb->get()->toArray();
    }

    public function getDeleted(string $type, ?int $deletedAfter = null): array
    {
        $qb = $this->query();

        $tableName = match ($type) {
            APIData::TYPE_PROJECTS => 'itk_projects_deleted',
            APIData::TYPE_TICKETS, APIData::TYPE_MILESTONES => 'itk_tickets_deleted',
            APIData::TYPE_TIMESHEETS => 'itk_timesheets_deleted',
            default => throw new \Exception("Invalid type $type"),
        };

        $qb->from($tableName, "entry");
        $qb->select(["entryId", "dateDeleted"]);

        if ($type === APIData::TYPE_MILESTONES) {
            $qb->where('type', '=', 'milestone');
        } else if ($type === APIData::TYPE_TICKETS) {
            $qb->where('type', '<>', 'milestone');
        }

        if ($deletedAfter !== null) {
            $qb->where("entry.dateDeleted", ">=", CarbonImmutable::createFromTimestamp($deletedAfter)->format(APIData::DATE_FORMAT));
        }

        return $qb->get()->toArray();
    }
}
