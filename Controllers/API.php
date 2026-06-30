<?php

namespace Leantime\Plugins\APIData\Controllers;

use Carbon\CarbonImmutable;
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

        // workYear/workMonth select a year or month on workDate as a half-open range.
        // If only workMonth is given, the current year is assumed.
        $workYear = isset($input['workYear']) && preg_match('/^\d{4}$/', (string) $input['workYear'])
            ? (int) $input['workYear']
            : null;
        $workMonth = isset($input['workMonth']) && is_numeric($input['workMonth']) && (int) $input['workMonth'] >= 1 && (int) $input['workMonth'] <= 12
            ? (int) $input['workMonth']
            : null;

        $workStart = null;
        $workEnd = null;
        if ($workYear !== null || $workMonth !== null) {
            $workYear ??= (int) CarbonImmutable::now()->format('Y');
            $start = CarbonImmutable::create($workYear, $workMonth ?? 1, 1, 0, 0, 0);
            $end = $workMonth !== null ? $start->addMonth() : $start->addYear();
            $workStart = $start->format(APIData::DATE_FORMAT);
            $workEnd = $end->format(APIData::DATE_FORMAT);
        }

        $results = $this->dataAPIService->getTimesheetTotals($groupBy, $from, $to, $projectIds, $workStart, $workEnd);

        return (new ResponseData(
            [
                'groupBy' => $groupBy,
                'from' => $from,
                'to' => $to,
                'projectIds' => $projectIds,
                'workYear' => $workYear,
                'workMonth' => $workMonth,
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
