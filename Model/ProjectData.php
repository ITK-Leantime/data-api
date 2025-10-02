<?php

namespace Leantime\Plugins\APIData\Model;

readonly class ProjectData
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
