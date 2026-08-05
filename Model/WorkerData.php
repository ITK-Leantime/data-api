<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class WorkerData
{
    public function __construct(
        public int $id,
        public ?string $email,
        // CONCAT() of the first and last name, and MySQL CONCAT returns NULL if
        // either part is NULL — a user with no surname has no name here.
        public ?string $name,
        public ?CarbonInterface $modified,
    ) {}
}
