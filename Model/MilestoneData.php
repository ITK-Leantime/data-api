<?php

namespace Leantime\Plugins\APIData\Model;

readonly class MilestoneData
{
    public function __construct(
        public int $id,
        public int $projectId,
        public string $name,
    ) {}
}
