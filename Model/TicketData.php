<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class TicketData
{
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
    ) {}
}
