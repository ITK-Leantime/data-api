<?php

namespace Leantime\Plugins\DataAPI\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Plugins\DataAPI\Model\MilestoneData;
use Leantime\Plugins\DataAPI\Model\ProjectData;
use Leantime\Plugins\DataAPI\Model\TicketData;
use Leantime\Plugins\DataAPI\Model\TimesheetData;

class DataAPI
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly TicketRepository $ticketRepository)
    {
    }


    public function install(): void
    {
        // Create tables.
    }

    public function uninstall(): void
    {
        // Remove tables.
    }

    private function query(): Builder
    {
        return app('db')->connection()->query();
    }

    public function getProjects(int $startId, int $limit, ?int $modifiedSinceTimestamp = null, ?array $ids = null): array
    {
        $qb = $this->query();
        $qb->select(["id", "name"]);
        $qb->from("zp_projects", "project");
        $qb->where("project.id", ">=", $startId);

        if ($modifiedSinceTimestamp !== null) {
            $qb->where("project.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp)->format($this::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("project.id", $ids);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        $values = $qb->get()->toArray();

        return array_map(function ($value) {
            return new ProjectData(
                $value->id,
                $value->name,
            );
        }, $values);
    }

    public function getMilestones(int $startId, int $limit, int $modifiedSinceTimestamp = null, ?array $ids = null, ?int $projectId = null): array
    {
        $qb = $this->query();
        $qb->select(["id", "headline", "projectId"]);
        $qb->from("zp_tickets", "ticket");
        $qb->where("ticket.id", ">=", $startId);
        $qb->where("ticket.type", "=", "milestone");

        if ($modifiedSinceTimestamp !== null) {
            $qb->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp)->format($this::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("ticket.id", $ids);
        }

        if ($projectId !== null) {
            $qb->where("ticket.projectId", $projectId);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        $values = $qb->get()->toArray();

        return array_map(function ($value) {
            return new MilestoneData(
                $value->id,
                $value->projectId,
                $value->headline,
            );
        }, $values);
    }

    public function getTickets(int $startId, int $limit, int $modifiedSinceTimestamp = null, array $ids = null, ?int $projectId = null): array
    {
        $qb = $this->query();
        $qb->select(["ticket.id", "ticket.headline", "ticket.projectId", "ticket.status", "ticket.planHours", "ticket.hourRemaining", "ticket.tags", "ticket.dateToFinish", "ticket.editTo", "ticket.milestoneid", "ticket.modified", "user.username"]);
        $qb->from("zp_tickets", "ticket");
        $qb->where("ticket.id", ">=", $startId);
        $qb->where("ticket.type", "<>", "milestone");
        $qb->leftJoin('zp_user as user', "user.id", "=", "ticket.userId");

        if ($modifiedSinceTimestamp !== null) {
            $qb->where("ticket.date", ">=", CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp)->format($this::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("ticket.id", $ids);
        }

        if ($projectId !== null) {
            $qb->where("ticket.projectId", $projectId);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        $values = $qb->get()->toArray();

        return array_map(function ($value) {
            $projectStatuses = $this->ticketRepository->getStateLabels($value->projectId);

            return new TicketData(
                $value->id,
                $value->projectId,
                $value->headline,
                $projectStatuses[$value->status]['statusType'] ?? null,
                // milestoneid=0 equals null.
                $value->milestoneid !== null && $value->milestoneid > 0 ? $value->milestoneid : null,
                !empty($value->tags) ? explode(",", $value->tags) : [],
                $value->username,
                $value->planHours,
                $value->hourRemaining,
                // "0000-00-00 00:00:00" equals null.
                $value->dateToFinish !== null && $value->dateToFinish !== "0000-00-00 00:00:00" ? CarbonImmutable::createFromFormat($this::DATE_FORMAT, $value->dateToFinish) : null,
                $value->editTo !== null && $value->editTo !== "0000-00-00 00:00:00" ? CarbonImmutable::createFromFormat($this::DATE_FORMAT, $value->editTo) : null,
                $value->modified !== null && $value->modified !== "0000-00-00 00:00:00" ? CarbonImmutable::createFromFormat($this::DATE_FORMAT, $value->modified) : null,
            );
        }, $values);
    }

    public function getTimesheets(int $startId, int $limit, ?int $modifiedSinceTimestamp = null, ?array $ids = null, ?int $ticketId = null): array
    {
        $qb = $this->query();
        $qb->from("zp_timesheets", "timesheet");
        $qb->select(["timesheet.id", "timesheet.description", "timesheet.hours", "timesheet.workDate", "timesheet.modified", "timesheet.ticketId", "user.username"]);
        $qb->where("timesheet.id", ">=", $startId);
        $qb->leftJoin('zp_user as user', "user.id", "=", "timesheet.userId");

        if ($modifiedSinceTimestamp !== null) {
            $qb->where("timesheet.modified", ">=", CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp)->format($this::DATE_FORMAT));
        }

        if ($ids !== null) {
            $qb->whereIn("timesheet.id", $ids);
        }

        if ($ticketId !== null) {
            $qb->where("timesheet.ticketId", $ticketId);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        $values = $qb->get()->toArray();

        return array_map(function ($value) {
            return new TimesheetData(
                $value->id,
                $value->ticketId,
                $value->description,
                $value->hours,
                $value->username,
                CarbonImmutable::createFromFormat(self::DATE_FORMAT, $value->workDate),
                CarbonImmutable::createFromFormat(self::DATE_FORMAT, $value->modified),
            );
        }, $values);
    }
}
