<?php

namespace Leantime\Plugins\APIData\Model;

readonly class TimesheetTotalData
{
    public function __construct(
        public string $period,
        public float $hours,
        public int $count,
    ) {}
}
