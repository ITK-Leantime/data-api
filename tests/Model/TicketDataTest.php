<?php

namespace Leantime\Plugins\APIData\Tests\Model;

use Leantime\Plugins\APIData\Model\TicketData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TicketData::class)]
final class TicketDataTest extends TestCase
{
    /**
     * `zp_tickets.headline` is `varchar(255) DEFAULT NULL` and `projectId` is
     * `int(11) DEFAULT NULL` in Leantime v3.9.7.
     */
    public function testAcceptsNullNameAndProjectId(): void
    {
        $ticket = $this->makeTicket(['projectId' => null, 'name' => null]);

        $this->assertNull($ticket->projectId);
        $this->assertNull($ticket->name);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeTicket(array $overrides = []): TicketData
    {
        return new TicketData(...[
            'id' => 4711,
            'projectId' => 92,
            'name' => 'Fix the thing',
            'status' => 'NEW',
            'milestoneId' => null,
            'tags' => [],
            'worker' => 'anne@aarhus.dk',
            'plannedHours' => null,
            'remainingHours' => null,
            'dueDate' => null,
            'resolutionDate' => null,
            'modified' => null,
            ...$overrides,
        ]);
    }
}
