<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

readonly class DeletedData
{
    public function __construct(
        public ?int $id,
        public ?CarbonInterface $deletedDate,
    ) {}
}
