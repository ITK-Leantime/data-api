<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\WorkerData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkerData::class)]
final class WorkerDataTest extends TestCase
{
    /**
     * The name is built with `CONCAT(firstname, ' ', lastname)`, and MySQL
     * CONCAT returns NULL if any argument is NULL — both columns are nullable
     * in Leantime v3.9.7, so a user without a surname has no name at all.
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
