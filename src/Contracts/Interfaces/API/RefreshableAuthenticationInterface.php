<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RefreshableAuthenticationInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

/**
 * Authentication whose credentials can be force-refreshed.
 *
 * ClientAbstract uses this after a 401 response: the request is retried
 * once with fresh credentials before the UnauthorizedException is
 * propagated (self-healing for server-side token invalidation).
 */
interface RefreshableAuthenticationInterface extends AuthenticationInterface {
    /**
     * Force-refresh the underlying credentials.
     *
     * Implementations must not throw for the "cannot refresh" case —
     * return false instead so the original error can propagate.
     *
     * @return bool True when new credentials were obtained
     */
    public function refresh(): bool;
}
