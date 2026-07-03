<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OAuth2TokenStoreInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

use APIToolkit\API\Authentication\OAuth2\OAuth2Token;

/**
 * Persistence boundary for OAuth2 tokens.
 *
 * The toolkit never stores tokens itself: consuming applications implement
 * this interface (e.g. encrypted database storage) and receive every token
 * update — including automatic refreshes — through save().
 */
interface OAuth2TokenStoreInterface {
    /**
     * Load the currently stored token, if any.
     */
    public function load(): ?OAuth2Token;

    /**
     * Persist a new or refreshed token.
     */
    public function save(OAuth2Token $token): void;

    /**
     * Remove the stored token (e.g. after revocation).
     */
    public function clear(): void;
}
