<?php

namespace Leantime\Plugins\APIData\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\APIData\Model\ResponseData;
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
        return new JsonResponse($this->getDeleted($input));
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

    public function workers(array $input): JsonResponse
    {
        return new JsonResponse($this->getResults($input, APIData::TYPE_WORKERS));
    }

    public function timesheetTotals(array $input): JsonResponse
    {
        return new JsonResponse($this->getTimesheetTotals($input));
    }

    private function getTimesheetTotals(array $input): array
    {
        $groupBy = ($input['groupBy'] ?? null) === APIData::GROUP_BY_WEEK
            ? APIData::GROUP_BY_WEEK
            : APIData::GROUP_BY_DAY;
        $from = isset($input['from']) ? (int) $input['from'] : null;
        $to = isset($input['to']) ? (int) $input['to'] : null;
        $projectIds = $input['projectIds'] ?? null;
        // Accept a year ("2026") or year-month ("2026-06") to filter on workDate.
        $workDate = isset($input['workDate']) && preg_match('/^\d{4}(-\d{2})?$/', (string) $input['workDate'])
            ? (string) $input['workDate']
            : null;

        $results = $this->dataAPIService->getTimesheetTotals($groupBy, $from, $to, $projectIds, $workDate);

        return (new ResponseData(
            [
                'groupBy' => $groupBy,
                'from' => $from,
                'to' => $to,
                'projectIds' => $projectIds,
                'workDate' => $workDate,
            ],
            count($results),
            $results,
        ))->toArray();
    }

    private function getDeleted(array $input): array
    {
        $types = $input['types'];
        $deleted = $input['deleted'] ?? null;

        $deletedEntries = [];
        $count = 0;

        foreach ($types as $type) {
            $deletedEntries[$type] = $this->dataAPIService->getDeleted($type, $deleted);
            $count = $count + count($deletedEntries[$type]);
        }

        return (new ResponseData(
            ['types' => $types],
            $count,
            $deletedEntries,
        ))->toArray();
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
            APIData::TYPE_WORKERS => $this->dataAPIService->getWorkers($start, $limit, $modifiedAfter, $ids),
        };

        return (new ResponseData(
            [
                'start' => $start,
                'limit' => $limit,
                'modifiedAfter' => $modifiedAfter,
                'ids' => $ids,
                'projectIds' => $projectIds,
            ],
            count($results),
            $results,
        ))->toArray();
    }
}
