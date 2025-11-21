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
        $sql = "
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
        ";

        // Use PDO for multi-statement SQL with parameter binding
        // We need to use PDO directly because Laravel's statement() method
        // may not handle multi-statement SQL properly
        $pdo = app('db')->connection()->getPdo();
        $stmn = $pdo->prepare($sql);

        $stmn->execute();

        $stmn->closeCursor();
    }

    public function uninstall(): void
    {
        $sql = "
        DROP TRIGGER itk_projects_deleted_trigger;
        DROP TRIGGER itk_tickets_deleted_trigger;
        DROP TRIGGER itk_timesheets_deleted_trigger;
        ";

        // Tables are not remove, to preserve data through install/uninstalls.
        // DROP TABLE `itk_projects_deleted`;
        // DROP TABLE `itk_tickets_deleted`;
        // DROP TABLE `itk_timesheets_deleted`;

        // Use PDO for multi-statement SQL with parameter binding
        // We need to use PDO directly because Laravel's statement() method
        // may not handle multi-statement SQL properly
        $pdo = app('db')->connection()->getPdo();
        $stmn = $pdo->prepare($sql);

        $stmn->execute();

        $stmn->closeCursor();
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
