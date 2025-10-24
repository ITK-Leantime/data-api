<?php

namespace Leantime\Plugins\APIData\Model;

class ResponseData
{
    public function __construct(public readonly array $parameters, public readonly int $resultsCount, public readonly array $results)
    {}

    public function toArray(): array
    {
        return [
            'parameters' => $this->parameters,
            'resultsCount' => $this->resultsCount,
            'results' => $this->resultsCount,
        ];
    }
}
