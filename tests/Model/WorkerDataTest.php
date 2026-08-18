<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\WorkerData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerData::class)]
final class WorkerDataTest extends TestCase
{
    /**
     * `zp_user.username`, `.firstname` and `.lastname` are all `NOT NULL` in
     * Leantime v3.9.7, so this is not a live TypeError. The name is still
     * nullable on purpose: `ApiDataRepository::getWorkers()` maps an all-blank
     * name to null rather than to a string of whitespace, and the remaining
     * models in this namespace are nullable throughout.
     */
    public function testAcceptsNullNameAndEmail(): void
    {
        $worker = new WorkerData(id: 57, email: null, name: null, modified: null);

        $this->assertSame(57, $worker->id);
        $this->assertNull($worker->email);
        $this->assertNull($worker->name);
        $this->assertNull($worker->modified);
    }
}
