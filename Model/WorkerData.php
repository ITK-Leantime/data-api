<?php

namespace Leantime\Plugins\APIData\Model;

/**
 * Data transfer object for a worker (user) exported through the API.
 */
readonly class WorkerData
{
    /**
     * Create the worker data transfer object.
     */
    public function __construct(
        public int $id,
        public string $email,
        public string $name,
    ) {
    }
}
