<?php

namespace Leantime\Plugins\APIData\Model;

class ResponseData
{
    /**
     * @param array<string, mixed>     $parameters   Request parameters echoed back to the caller.
     * @param int                      $resultsCount Number of results.
     * @param array<int|string, mixed> $results      The result payload.
     */
    public function __construct(public readonly array $parameters, public readonly int $resultsCount, public readonly array $results)
    {
    }

    /**
     * Convert the response to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'parameters' => $this->parameters,
            'resultsCount' => $this->resultsCount,
            'results' => $this->results,
        ];
    }
}
