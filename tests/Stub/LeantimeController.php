<?php

namespace Leantime\Core\Controller;

/**
 * Test-only stand-in for Leantime's base controller.
 *
 * Leantime core is not a composer dependency of this plugin, but
 * `Controllers\API` extends this class, so it has to exist for static analysis
 * to resolve the class hierarchy.
 *
 * Copied from Leantime v3.9.7, where the base class declares no `init()` of its
 * own — the constructor calls `app()->call([$this, 'init'])` if the subclass
 * happens to define one, which is how `Controllers\API` gets its service
 * injected. Nothing detects drift if Leantime changes that, so re-check it
 * against `app/Core/Controller/Controller.php` when upgrading Leantime.
 */
abstract class Controller
{
}
