<?php

namespace Leantime\Plugins\APIData\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Leantime\Plugins\APIData\Model\DeletedData;
use Leantime\Plugins\APIData\Model\MilestoneData;
use Leantime\Plugins\APIData\Model\ProjectData;
use Leantime\Plugins\APIData\Model\TicketData;
use Leantime\Plugins\APIData\Model\TimesheetData;
use Leantime\Plugins\APIData\Repositories\ApiDataRepository;

class APIData
{
    public const TYPE_PROJECTS = 'projects';
    public const TYPE_MILESTONES = 'milestones';
    public const TYPE_TICKETS = 'tickets';
    public const TYPE_TIMESHEETS = 'timesheets';

    public const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly ApiDataRepository $apiDataRepository,
    ) {}

    public function install(): void
    {
        $this->apiDataRepository->install();
    }

    public function uninstall(): void
    {
        $this->apiDataRepository->uninstall();
    }

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
                $this->getCarbonFromDatabaseValue($value->workDate),
                $this->getCarbonFromDatabaseValue($value->modified),
                $value->kind,
            );
        }, $values);
    }

    public function getDeleted(string $type, ?int $deletedAfter = null): array
    {
        $values = $this->apiDataRepository->getDeleted($type, $deletedAfter);

        return array_map(fn ($entry) => new DeletedData(
            $entry->entryId,
            $this->getCarbonFromDatabaseValue($entry->dateDeleted),
        ), $values);
    }

    private function getCarbonFromDatabaseValue($value): ?CarbonImmutable
    {
        // "0000-00-00 00:00:00" equals null.
        return $value !== null && $value !== "0000-00-00 00:00:00"
            ? CarbonImmutable::createFromFormat(APIData::DATE_FORMAT, $value, 'UTC')
            : null;
    }

    private function getMilestoneId(mixed $value)
    {
        // milestoneid=0 equals null.
        return $value->milestoneid !== null && $value->milestoneid > 0 ? $value->milestoneid : null;
    }
}
