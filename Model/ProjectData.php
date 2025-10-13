<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class ProjectData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?CarbonInterface $modified,
    ) {}
}
