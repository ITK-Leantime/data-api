<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class MilestoneData
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $name,
        public ?CarbonInterface $modified,
    ) {}
}
