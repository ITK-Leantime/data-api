<?php

namespace Leantime\Plugins\APIData\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\APIData\Services\APIData;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * DataAPI Controller for DataAPI plugin.
 */
class API extends Controller
{
    private const TYPE_PROJECTS = 'projects';
    private const TYPE_MILESTONES = 'milestones';
    private const TYPE_TICKETS = 'tickets';
    private const TYPE_TIMESHEETS = 'timesheets';

    private APIData $dataAPIService;

    public function init(APIData $dataAPIService): void
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
        $limit = (int) ($input['limit'] ?? 100);
        $modified = $input['modified'] ?? null;
        $ids = $input['ids'] ?? null;
        $projectIds = $input['projectIds'] ?? null;

        $results = match ($type) {
            $this::TYPE_PROJECTS => $this->dataAPIService->getProjects($start, $limit, $modified, $ids),
            $this::TYPE_MILESTONES => $this->dataAPIService->getMilestones($start, $limit, $modified, $ids, $projectIds),
            $this::TYPE_TICKETS => $this->dataAPIService->getTickets($start, $limit, $modified, $ids, $projectIds),
            $this::TYPE_TIMESHEETS => $this->dataAPIService->getTimesheets($start, $limit, $modified, $ids, $projectIds),
        };

        return [
            'parameters' => [
                'start' => $start,
                'limit' => $limit,
                'modified' => $modified,
                'ids' => $ids,
                'projectIds' => $projectIds,
            ],
            'resultsCount' => count($results),
            'results' => $results,
        ];
    }
}
