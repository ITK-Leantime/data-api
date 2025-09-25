<?php

namespace Leantime\Plugins\DataAPI\Model;

readonly class MilestoneData
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $name,
    ) {}
}
