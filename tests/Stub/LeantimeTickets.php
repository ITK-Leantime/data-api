<?php

namespace Leantime\Domain\Tickets\Repositories;

/**
 * Test-only stand-in for Leantime's ticket repository.
 *
 * Leantime core is not a composer dependency of this plugin, but
 * `Services\APIData` type-hints this class, so it has to exist for tests to
 * construct the service. Only `getStateLabels()` is declared, since that is the
 * only method the plugin calls.
 *
 * Copied from Leantime v3.9.7. Nothing detects drift if Leantime changes the
 * signature, so re-check it against
 * `app/Domain/Tickets/Repositories/Tickets.php` when upgrading Leantime.
 *
 * `getStateLabels()` returns an array keyed by the integer status id, where each
 * element looks like:
 *
 *     3 => [
 *         'name' => 'status.new',
 *         'class' => 'label-info',
 *         'statusType' => 'NEW',
 *         'kanbanCol' => true,
 *         'sortKey' => 1,
 *     ]
 *
 * Note that Leantime falls back to `session('currentProject')` when $projectId
 * is null, which is why `APIData::getTickets()` must not call this with a null
 * project id.
 */
class Tickets
{
    public function getStateLabels($projectId = null): array
    {
        throw new \LogicException(
            'Leantime\Domain\Tickets\Repositories\Tickets is a test stub and must be mocked.'
        );
    }
}
