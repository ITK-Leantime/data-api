<?php

namespace Leantime\Plugins\DataAPI\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\DataAPI\Services\DataAPI;
use Psy\Util\Json;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * DataAPI Controller for DataAPI plugin.
 */
class DataAPIController extends Controller
{
    private const TYPE_PROJECTS = 'projects';
    private const TYPE_MILESTONES = 'milestones';
    private const TYPE_TICKETS = 'tickets';
    private const TYPE_TIMESHEETS = 'timesheets';

    private DataAPI $dataAPIService;

    public function init(DataAPI $dataAPIService): void
    {
        $this->dataAPIService = $dataAPIService;
    }

    public function projects(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, $this::TYPE_PROJECTS));
    }

    public function milestones(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, $this::TYPE_MILESTONES));
    }

    public function tickets(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, $this::TYPE_TICKETS));
    }

    public function timesheets(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, $this::TYPE_TIMESHEETS));
    }

    private function getResults(array $input, string $type): array
    {
        $start = (int) ($input['start'] ?? 0);
        $limit = (int) ($input['limit'] ?? 10);
        $modified = $input['modified'] ?? null;
        $ids = $input['id'] ?? null;
        $projectId = $input['project_id'] ?? null;

        $results = match ($type) {
            $this::TYPE_PROJECTS => $this->dataAPIService->getProjects($start, $limit, $modified, $ids),
            $this::TYPE_MILESTONES => $this->dataAPIService->getMilestones($start, $limit, $modified, $ids, $projectId),
            $this::TYPE_TICKETS => $this->dataAPIService->getTickets($start, $limit, $modified, $ids, $projectId),
            $this::TYPE_TIMESHEETS => $this->dataAPIService->getTimesheets($start, $limit, $modified, $ids, $projectId),
        };

        return [
            'parameters' => [
                'start' => $start,
                'limit' => $limit,
                'modified' => $modified,
                'id' => $ids,
            ],
            'results' => $results,
        ];
    }
}
