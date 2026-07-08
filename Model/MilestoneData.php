<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * Data transfer object for a milestone exported through the API.
 */
readonly class MilestoneData
{
    /**
     * Create the milestone data transfer object.
     */
    public function __construct(
        public int $id,
        public int $projectId,
        public string $name,
        public ?CarbonInterface $modified,
    ) {
    }
}
