<?php

namespace Leantime\Plugins\APIData\Model;

use Carbon\CarbonInterface;

/**
 * A single deletion. `deletionId` is the deleted table's own row id, which the
 * caller feeds back as `start` to page on; `id` is the id of the entity that was
 * deleted, and is nullable because the tracking tables allow it.
 */
readonly class DeletedData
{
    /**
     * Create the deleted-entity data transfer object.
     */
    public function __construct(
        public ?int $deletionId,
        public ?int $id,
        public ?CarbonInterface $deletedDate,
    ) {
    }
}
