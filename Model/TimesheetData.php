<?php

namespace Leantime\Plugins\DataAPI\Model;

use Carbon\CarbonInterface;

readonly class TimesheetData
{
    public function __construct(
        public int $id,
        public int $ticketId,
        public string $description,
        public float $hours,
        public string $username,
        public ?CarbonInterface $workDate = null,
        public ?CarbonInterface $modified = null,
    ) {}
}
