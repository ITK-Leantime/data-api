<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * Data transfer object for a project exported through the API.
 */
readonly class ProjectData
{
    /**
     * Create the project data transfer object.
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?CarbonInterface $modified,
    ) {
    }
}
