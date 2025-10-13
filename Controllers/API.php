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
    private APIData $dataAPIService;

    public function init(APIData $dataAPIService): void
    {
        $this->dataAPIService = $dataAPIService;
    }

    public function deleted(array $input): JsonResponse
    {
        return new JsonResponse([
            $this->getDeleted($input)
        ]);
    }

    public function projects(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, APIData::TYPE_PROJECTS));
    }

    public function milestones(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, APIData::TYPE_MILESTONES));
    }

    public function tickets(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, APIData::TYPE_TICKETS));
    }

    public function timesheets(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, APIData::TYPE_TIMESHEETS));
    }

    private function getDeleted(array $input): array
    {
        $types = $input['types'];
        $deleted = $input['deleted'] ?? null;

        $deletedEntries = [];

        foreach ($types as $type) {
            $deletedEntries[$type] = $this->dataAPIService->getDeleted($type, $deleted);
        }

        return [
            'parameters' => [
                'types' => $types,
            ],
            'resultsCount' => array_reduce(
                $types,
                function ($carry, $type) use ($deletedEntries) {
                    $carry[$type] = count($deletedEntries[$type]);
                    return $carry;
                },
                []
            ),
            'results' => $deletedEntries,
        ];
    }

    private function getResults(array $input, string $type): array
    {
        $start = (int) ($input['start'] ?? 0);
        $limit = (int) ($input['limit'] ?? 100);
        $modifiedAfter = $input['modifiedAfter'] ?? null;
        $ids = $input['ids'] ?? null;
        $projectIds = $input['projectIds'] ?? null;

        $results = match ($type) {
            APIData::TYPE_PROJECTS => $this->dataAPIService->getProjects($start, $limit, $modifiedAfter, $ids),
            APIData::TYPE_MILESTONES => $this->dataAPIService->getMilestones($start, $limit, $modifiedAfter, $ids, $projectIds),
            APIData::TYPE_TICKETS => $this->dataAPIService->getTickets($start, $limit, $modifiedAfter, $ids, $projectIds),
            APIData::TYPE_TIMESHEETS => $this->dataAPIService->getTimesheets($start, $limit, $modifiedAfter, $ids, $projectIds),
        };

        return [
            'parameters' => [
                'start' => $start,
                'limit' => $limit,
                'modifiedAfter' => $modifiedAfter,
                'ids' => $ids,
                'projectIds' => $projectIds,
            ],
            'resultsCount' => count($results),
            'results' => $results,
        ];
    }
}
