<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\ProjectData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectData::class)]
final class ProjectDataTest extends TestCase
{
    /**
     * `zp_projects.name` is `varchar(100) DEFAULT NULL` in Leantime v3.9.7.
     */
    public function testAcceptsNullName(): void
    {
        $project = new ProjectData(id: 92, name: null, modified: null);

        $this->assertSame(92, $project->id);
        $this->assertNull($project->name);
    }
}
