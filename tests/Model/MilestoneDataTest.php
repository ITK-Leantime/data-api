<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\MilestoneData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MilestoneData::class)]
final class MilestoneDataTest extends TestCase
{
    /**
     * Milestones are rows in `zp_tickets`, where `headline` is
     * `varchar(255) DEFAULT NULL` and `projectId` is `int(11) DEFAULT NULL` in
     * Leantime v3.9.7.
     */
    public function testAcceptsNullNameAndProjectId(): void
    {
        $milestone = new MilestoneData(id: 5, projectId: null, name: null, modified: null);

        $this->assertSame(5, $milestone->id);
        $this->assertNull($milestone->projectId);
        $this->assertNull($milestone->name);
    }
}
