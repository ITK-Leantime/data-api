<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * Data transfer object for a deleted-entity entry exported through the API.
 */
class DeletedData
{
    /**
     * Create the deleted-entity data transfer object.
     */
    public function __construct(
        public int $id,
        public CarbonInterface $deletedDate,
    ) {
    }
}
