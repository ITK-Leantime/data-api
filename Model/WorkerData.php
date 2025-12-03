<?php


namespace Leantime\Plugins\APIData\Model;


readonly class WorkerData
{
    public function __construct(
        public int                  $id,
        public string              $email,
        public string              $name,

    )
    {
    }
}
