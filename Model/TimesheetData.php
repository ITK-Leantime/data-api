<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class TimesheetData
{
    public function __construct(
        public int $id,
        public int $ticketId,
        public int $projectId,
        public string $description,
        public float $hours,
        public string $username,
        public ?CarbonInterface $workDate = null,
        public ?CarbonInterface $modified = null,
        public string $kind,
    ) {}
}
