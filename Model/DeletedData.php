<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

class DeletedData
{
    public function __construct(
        public int $id,
        public CarbonInterface $deletedDate,
    ) {}
}
