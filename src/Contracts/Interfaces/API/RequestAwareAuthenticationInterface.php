<?php
/*
 * Created on   : Thu Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequestAwareAuthenticationInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

/**
 * Authentication scheme whose headers depend on the concrete request,
 * e.g. per-request HMAC signatures over method, resource and/or body.
 *
 * When an authentication implements this interface, ClientAbstract calls
 * getAuthHeadersFor() with the request context instead of getAuthHeaders().
 */
interface RequestAwareAuthenticationInterface extends AuthenticationInterface {
    /**
     * Get the authentication headers for a specific request.
     *
     * @param string $method HTTP method (GET, POST, ...)
     * @param string $uri Request URI as passed to the client (relative to the base URL, without merged default query parameters)
     * @param string|null $body Raw request body, or null if the request has none
     * @return array<string, string>
     */
    public function getAuthHeadersFor(string $method, string $uri, ?string $body = null): array;
}
