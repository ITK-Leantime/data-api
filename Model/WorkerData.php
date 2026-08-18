<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class WorkerData
{
    public function __construct(
        public int $id,
        public ?string $email,
        // getWorkers() maps an all-blank name to null rather than to a string
        // of whitespace, so a user with no name at all has none here.
        public ?string $name,
        public ?CarbonInterface $modified,
    ) {}
}
