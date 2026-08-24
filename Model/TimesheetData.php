<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * A single timesheet entry, as the timesheets endpoint exports it.
 */
readonly class TimesheetData
{
    /**
     * Create the timesheet data transfer object.
     */
    public function __construct(
        public int $id,
        public ?int $ticketId,
        public ?int $projectId,
        public ?string $description,
        public float $hours,
        public ?int $userId,
        public ?string $username,
        public ?string $kind,
        public ?CarbonInterface $workDate = null,
        public ?CarbonInterface $modified = null,
    ) {
    }
}
