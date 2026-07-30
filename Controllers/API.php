<?php

namespace Leantime\Plugins\APIData\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\APIData\Model\DeletedRequestParameters;
use Leantime\Plugins\APIData\Model\InvalidRequestException;
use Leantime\Plugins\APIData\Model\RequestParameters;
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
        return $this->respond(fn () => $this->getDeleted($input));
    }

    public function projects(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_PROJECTS));
    }

    public function milestones(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_MILESTONES));
    }

    public function tickets(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_TICKETS));
    }

    public function timesheets(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_TIMESHEETS));
    }

    public function workers(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_WORKERS));
    }

    /**
     * A parameter the caller got wrong is their error, not ours, so it answers
     * 400 with the reason instead of Leantime's 500 error page.
     */
    private function respond(callable $resolve): JsonResponse
    {
        try {
            return new JsonResponse($resolve());
        } catch (InvalidRequestException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    private function getDeleted(array $input): array
    {
        $parameters = DeletedRequestParameters::fromInput($input);

        $deletedEntries = [];
        $count = 0;

        foreach ($parameters->types as $type) {
            $deletedEntries[$type] = $this->dataAPIService->getDeleted($type, $parameters->deleted);
            $count = $count + count($deletedEntries[$type]);
        }

        return (new ResponseData(
            $parameters->toArray(),
            $count,
            $deletedEntries,
        ))->toArray();
    }

    private function getResults(array $input, string $type): array
    {
        $parameters = RequestParameters::fromInput($input);

        $results = match ($type) {
            APIData::TYPE_PROJECTS => $this->dataAPIService->getProjects($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids),
            APIData::TYPE_MILESTONES => $this->dataAPIService->getMilestones($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_TICKETS => $this->dataAPIService->getTickets($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_TIMESHEETS => $this->dataAPIService->getTimesheets($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_WORKERS => $this->dataAPIService->getWorkers($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids),
        };

        return (new ResponseData(
            $parameters->toArray(),
            count($results),
            $results,
        ))->toArray();
    }
}
