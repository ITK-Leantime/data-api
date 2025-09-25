<?php

namespace Leantime\Plugins\DataAPI\Model;

readonly class ProjectData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
