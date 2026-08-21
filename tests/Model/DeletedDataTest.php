<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\DeletedData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeletedData::class)]
final class DeletedDataTest extends TestCase
{
    /**
     * The plugin's own DDL declares `entryId int(11) DEFAULT NULL`, and
     * `$deletedDate` is fed from `APIData::getCarbonFromDatabaseValue()`, whose
     * return type is `?CarbonImmutable`. The non-nullable declarations were a
     * type mismatch regardless of what the data looks like.
     */
    public function testAcceptsNullIdAndDeletedDate(): void
    {
        $deleted = new DeletedData(deletionId: null, id: null, deletedDate: null);

        $this->assertNull($deleted->deletionId);
        $this->assertNull($deleted->id);
        $this->assertNull($deleted->deletedDate);
    }

    /**
     * `deletionId` is what a client pages on, so it is a field of its own rather
     * than a reuse of `id`, which carries the deleted entity's id.
     */
    public function testCarriesTheDeletionIdAlongsideTheDeletedEntityId(): void
    {
        $deleted = new DeletedData(deletionId: 82, id: 4711, deletedDate: null);

        $this->assertSame(82, $deleted->deletionId);
        $this->assertSame(4711, $deleted->id);
    }
}
