<?php

namespace Leantime\Plugins\APIData\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Plugins\APIData\Model\BadRequestException;
use Leantime\Plugins\APIData\Model\DeletedRequestParameters;
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

    /**
     * Inject the service, which Leantime resolves for us.
     */
    public function init(APIData $dataAPIService): void
    {
        $this->dataAPIService = $dataAPIService;
    }

    /**
     * Answer with a page of deletions of one entity type.
     *
     * @param array<string, mixed> $input
     */
    public function deleted(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getDeleted($input));
    }

    /**
     * Answer with a page of projects.
     *
     * @param array<string, mixed> $input
     */
    public function projects(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_PROJECTS));
    }

    /**
     * Answer with a page of milestones.
     *
     * @param array<string, mixed> $input
     */
    public function milestones(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_MILESTONES));
    }

    /**
     * Answer with a page of tickets, milestones excluded.
     *
     * @param array<string, mixed> $input
     */
    public function tickets(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_TICKETS));
    }

    /**
     * Answer with a page of timesheet entries.
     *
     * @param array<string, mixed> $input
     */
    public function timesheets(array $input): JsonResponse
    {
        return $this->respond(fn () => $this->getResults($input, APIData::TYPE_TIMESHEETS));
    }

    /**
     * Answer with a page of users, api users excluded.
     *
     * @param array<string, mixed> $input
     */
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
        } catch (BadRequestException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Resolve the deleted endpoint's parameters and wrap the page in a response body.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function getDeleted(array $input): array
    {
        $parameters = DeletedRequestParameters::fromInput($input);

        $results = $this->dataAPIService->getDeleted(
            $parameters->type,
            $parameters->start,
            $parameters->limit,
            $parameters->deletedAfter,
        );

        return (new ResponseData(
            $parameters->toArray(),
            count($results),
            $results,
        ))->toArray();
    }

    /**
     * Resolve an entity endpoint's parameters and wrap the page in a response body.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function getResults(array $input, string $type): array
    {
        $parameters = RequestParameters::fromInput($input);

        $results = match ($type) {
            APIData::TYPE_PROJECTS => $this->dataAPIService->getProjects($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids),
            APIData::TYPE_MILESTONES => $this->dataAPIService->getMilestones($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_TICKETS => $this->dataAPIService->getTickets($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_TIMESHEETS => $this->dataAPIService->getTimesheets($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids, $parameters->projectIds),
            APIData::TYPE_WORKERS => $this->dataAPIService->getWorkers($parameters->start, $parameters->limit, $parameters->modifiedAfter, $parameters->ids),
            // Every caller passes an APIData::TYPE_* constant, so reaching this
            // is a bug here rather than bad input, and is not a 400.
            default => throw new \InvalidArgumentException("Invalid type: $type"),
        };

        return (new ResponseData(
            $parameters->toArray(),
            count($results),
            $results,
        ))->toArray();
    }
}
