<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Psr16TokenStore.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * OAuth2 token store backed by any PSR-16 cache (Redis, Memcached, APCu, …).
 *
 * Enables sharing a token across processes/hosts. The cache key defaults to
 * "oauth2_token" but should be namespaced per tenant/client when a cache is
 * shared. The token is stored as its toArray() JSON.
 */
class Psr16TokenStore implements OAuth2TokenStoreInterface {
    private CacheInterface $cache;
    private string $key;

    public function __construct(CacheInterface $cache, string $key = 'oauth2_token') {
        $this->cache = $cache;
        $this->key = $key;
    }

    public function load(): ?OAuth2Token {
        $raw = $this->cache->get($this->key);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return OAuth2Token::fromArray($data);
    }

    public function save(OAuth2Token $token): void {
        // Expire the cache entry a little after the token itself so a stale
        // entry is never served; tokens without expiry are cached without TTL.
        $ttl = null;
        $expiresAt = $token->getExpiresAt();
        if ($expiresAt !== null) {
            $ttl = max(1, $expiresAt->getTimestamp() - time() + 60);
        }

        $this->cache->set($this->key, (string) json_encode($token->toArray()), $ttl);
    }

    public function clear(): void {
        $this->cache->delete($this->key);
    }
}
