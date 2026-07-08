<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * Data transfer object for a timesheet exported through the API.
 */
readonly class TimesheetData
{
    /**
     * Create the timesheet data transfer object.
     */
    public function __construct(
        public int $id,
        public int $ticketId,
        public int $projectId,
        public ?string $description,
        public float $hours,
        public string $username,
        public string $kind,
        public ?CarbonInterface $workDate = null,
        public ?CarbonInterface $modified = null,
    ) {
    }
}
