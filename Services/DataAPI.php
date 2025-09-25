<?php

namespace Leantime\Plugins\DataAPI\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Leantime\Plugins\DataAPI\Model\MilestoneData;
use Leantime\Plugins\DataAPI\Model\ProjectData;

class DataAPI
{
    public function install(): void
    {
        // Repo call to create tables.
    }

    public function uninstall(): void
    {
        // Remove tables
    }

    private function query(): Builder
    {
        return app('db')->connection()->query();
    }

    public function getProjects(int $startId, int $limit, ?int $modifiedSinceTimestamp = null, array $ids = null): array
    {
        $qb = $this->query();
        $qb->select(["id", "name"]);
        $qb->from("zp_projects", "project");
        $qb->where("project.id", ">=", $startId);

        if ($modifiedSinceTimestamp !== null) {
            $modifiedSince = CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp);
            $qb->where("project.modified", ">=", $modifiedSince->format('Y-m-d H:i:s'));
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

    public function getMilestones(int $startId, int $limit, int $modifiedSinceTimestamp = null, array $ids = null): array
    {
        $qb = $this->query();
        $qb->select(["id", "headline", "name"]);
        $qb->from("zp_tickets", "ticket");
        $qb->where("ticket.id", ">=", $startId);
        $qb->where("ticket.type", "=", "milestone");

        if ($modifiedSinceTimestamp !== null) {
            $modifiedSince = CarbonImmutable::createFromTimestamp($modifiedSinceTimestamp);
            $qb->where("ticket.date", ">=", $modifiedSince->format('Y-m-d H:i:s'));
        }

        if ($ids !== null) {
            $qb->whereIn("ticket.id", $ids);
        }

        $qb->orderBy("id", "ASC");
        $qb->limit($limit);

        $values = $qb->get()->toArray();

        return array_map(function ($value) {
            return new MilestoneData(
                $value['id'],
                $value['projectId'],
                $value['headline'],
            );
        }, $values);
    }
}
