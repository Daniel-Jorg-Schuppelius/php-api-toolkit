<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InMemoryTokenStore.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;

/**
 * Non-persistent token store for tests, scripts and short-lived processes.
 */
class InMemoryTokenStore implements OAuth2TokenStoreInterface {
    protected ?OAuth2Token $token;

    public function __construct(?OAuth2Token $token = null) {
        $this->token = $token;
    }

    public function load(): ?OAuth2Token {
        return $this->token;
    }

    public function save(OAuth2Token $token): void {
        $this->token = $token;
    }

    public function clear(): void {
        $this->token = null;
    }
}
