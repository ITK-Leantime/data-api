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
use Leantime\Plugins\APIData\Model\WorkerData;
use Leantime\Plugins\APIData\Repositories\ApiDataRepository;
use Leantime\Plugins\APIData\Repositories\SchemaRepository;

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
        private readonly SchemaRepository $schemaRepository,
    ) {}

    /**
     * Leantime calls this on every install, and offers no separate upgrade hook,
     * so SchemaRepository::install() has to be idempotent.
     */
    public function install(): void
    {
        $this->schemaRepository->install();
    }

    public function uninstall(): void
    {
        $this->schemaRepository->uninstall();
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

    public function getMilestones(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
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

    public function getTickets(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null, ?array $projectIds = null): array
    {
        $values = $this->apiDataRepository->getTickets($startId, $limit, $modifiedAfter, $ids, $projectIds);

        // Tickets arrive in batches from the same handful of projects, so the
        // labels are looked up once per project instead of once per ticket. Kept
        // local to the call, since labels can change between requests.
        $statusesByProject = [];

        return array_map(function ($value) use (&$statusesByProject) {
            // Asked for labels without a project id, Leantime falls back to
            // session('currentProject'), which would resolve the status against
            // an unrelated project.
            $projectStatuses = $value->projectId !== null
                ? $statusesByProject[$value->projectId] ??= $this->ticketRepository->getStateLabels($value->projectId)
                : [];

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
            // Named arguments: CarbonImmutable has a __toString(), so a
            // mis-ordered date would be coerced into one of the string
            // parameters instead of raising a TypeError.
            return new TimesheetData(
                id: $value->id,
                ticketId: $value->ticketId,
                projectId: $value->projectId,
                description: $value->description,
                hours: $value->hours,
                userId: $value->userId,
                username: $value->username,
                kind: $value->kind,
                workDate: $this->getCarbonFromDatabaseValue($value->workDate),
                modified: $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    public function getWorkers(int $startId, int $limit, ?int $modifiedAfter = null, ?array $ids = null): array
    {
        $values = $this->apiDataRepository->getWorkers($startId, $limit, $modifiedAfter, $ids);

        return array_map(function ($value) {
            return new WorkerData(
                id: $value->id,
                email: $value->username,
                name: $value->name,
                modified: $this->getCarbonFromDatabaseValue($value->modified),
            );
        }, $values);
    }

    public function getDeleted(string $type, int $startId, int $limit, ?int $deletedAfter = null): array
    {
        $values = $this->apiDataRepository->getDeleted($type, $startId, $limit, $deletedAfter);

        // Named arguments: the row's `id` is the deletion's own id and `entryId`
        // the deleted entity's, and both are ints, so a swap would map cleanly
        // onto the wrong field instead of raising a TypeError.
        return array_map(fn ($entry) => new DeletedData(
            deletionId: $entry->id,
            id: $entry->entryId,
            deletedDate: $this->getCarbonFromDatabaseValue($entry->dateDeleted),
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
