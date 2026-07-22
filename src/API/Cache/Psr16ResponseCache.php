<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Psr16ResponseCache.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Cache;

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Transparent GET response cache with conditional requests, backed by any
 * PSR-16 cache. Registers itself on a client's middleware hooks:
 *
 *   (new Psr16ResponseCache($cache))->register($client);
 *
 * Behaviour per GET:
 *  - a still-fresh cached entry (within $ttl) is served without a network call;
 *  - a stale entry with an ETag/Last-Modified triggers a conditional request
 *    (If-None-Match / If-Modified-Since); a 304 is served from cache and its
 *    freshness renewed, a 200 refreshes the stored body and validators.
 *
 * Only GET is cached, and only 200 responses are stored.
 */
class Psr16ResponseCache {
    private CacheInterface $cache;
    private int $ttl;
    private string $prefix;

    /**
     * @param int $ttl Freshness window in seconds for serving without revalidation
     */
    public function __construct(CacheInterface $cache, int $ttl = 60, string $prefix = 'httpcache_') {
        $this->cache = $cache;
        $this->ttl = max(0, $ttl);
        $this->prefix = $prefix;
    }

    public function register(ClientAbstract $client): void {
        $client->onRequest([$this, 'onRequest']);
        $client->onResponse([$this, 'onResponse']);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|ResponseInterface
     */
    public function onRequest(string $method, string $uri, array $options): array|ResponseInterface {
        if (strtoupper($method) !== 'GET') {
            return $options;
        }

        $entry = $this->load($uri);
        if ($entry === null) {
            return $options;
        }

        if (isset($entry['stored_at']) && (time() - (int) $entry['stored_at']) < $this->ttl) {
            // Still fresh: serve from cache without a network round-trip.
            return $this->toResponse($entry);
        }

        // Stale but revalidatable: attach conditional-request headers.
        $headers = $options['headers'] ?? [];
        if (($entry['etag'] ?? '') !== '') {
            $headers['If-None-Match'] = $entry['etag'];
        }
        if (($entry['last_modified'] ?? '') !== '') {
            $headers['If-Modified-Since'] = $entry['last_modified'];
        }
        $options['headers'] = $headers;

        return $options;
    }

    public function onResponse(ResponseInterface $response, string $method, string $uri): ResponseInterface {
        if (strtoupper($method) !== 'GET') {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status === 304) {
            $entry = $this->load($uri);
            if ($entry !== null) {
                $entry['stored_at'] = time();
                $this->store($uri, $entry); // renew freshness
                return $this->toResponse($entry);
            }

            return $response;
        }

        if ($status === 200) {
            $this->store($uri, [
                'body' => (string) $response->getBody(),
                'etag' => $response->getHeaderLine('ETag'),
                'last_modified' => $response->getHeaderLine('Last-Modified'),
                'content_type' => $response->getHeaderLine('Content-Type'),
                'stored_at' => time(),
            ]);
            if ($response->getBody()->isSeekable()) {
                $response->getBody()->rewind();
            }
        }

        return $response;
    }

    private function key(string $uri): string {
        return $this->prefix . hash('sha256', $uri);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load(string $uri): ?array {
        $raw = $this->cache->get($this->key($uri));
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function store(string $uri, array $entry): void {
        // Keep the entry around a bit past its freshness so revalidation stays
        // possible (validators outlive the fresh window).
        $this->cache->set($this->key($uri), (string) json_encode($entry), $this->ttl > 0 ? $this->ttl * 10 : null);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function toResponse(array $entry): ResponseInterface {
        $headers = [];
        if (($entry['content_type'] ?? '') !== '') {
            $headers['Content-Type'] = $entry['content_type'];
        }
        $headers['X-Cache'] = 'HIT';

        return new Response(200, $headers, (string) ($entry['body'] ?? ''));
    }
}
