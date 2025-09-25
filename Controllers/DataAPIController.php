<?php

namespace Leantime\Plugins\DataAPI\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\DataAPI\Services\DataAPI;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * DataAPI Controller for DataAPI plugin.
 */
class DataAPIController extends Controller
{
    private DataAPI $dataAPIService;

    public function init(DataAPI $dataAPIService): void
    {
        $this->dataAPIService = $dataAPIService;
    }

    public function projects(array $input): JsonResponse
    {
        $start = (int) ($input['start'] ?? 0);
        $limit = (int) ($input['limit'] ?? 10);
        $modified = $input['modified'] ?? null;
        $ids = (array) ($input['id'] ?? null);

        $projects = $this->dataAPIService->getProjects($start, $limit, $modified, $ids);

        return new JsonResponse([
            'parameters' => [
                'start' => $start,
                'limit' => $limit,
                'modified' => $modified,
                'ids' => $ids,
            ],
            'results' => $projects,
        ]);
    }

    public function milestones(array $input): JsonResponse
    {
        $start = (int) ($input['start'] ?? 0);
        $limit = (int) ($input['limit'] ?? 10);
        $modified = $input['modified'] ?? null;
        $ids = (array) ($input['id'] ?? null);

        $milestones = $this->dataAPIService->getMilestones($start, $limit, $modified, $ids);

        return new JsonResponse([
            'parameters' => [
                'start' => $start,
                'limit' => $limit,
                'modified' => $modified,
                'ids' => $ids,
            ],
            'results' => $milestones,
        ]);
    }
}
