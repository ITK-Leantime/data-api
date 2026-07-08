<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * Data transfer object for a ticket exported through the API.
 */
readonly class TicketData
{
    /**
     * @param int                  $id
     * @param int                  $projectId
     * @param string               $name
     * @param string|null          $status
     * @param int|null             $milestoneId
     * @param array<int, string>   $tags
     * @param string|null          $worker
     * @param float|null           $plannedHours
     * @param float|null           $remainingHours
     * @param CarbonInterface|null $dueDate
     * @param CarbonInterface|null $resolutionDate
     * @param CarbonInterface|null $modified
     */
    public function __construct(
        public int $id,
        public int $projectId,
        public string $name,
        public ?string $status,
        public ?int $milestoneId,
        public array $tags,
        public ?string $worker,
        public ?float $plannedHours,
        public ?float $remainingHours,
        public ?CarbonInterface $dueDate,
        public ?CarbonInterface $resolutionDate,
        public ?CarbonInterface $modified,
    ) {
    }
}
