<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TokenStoreTest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace Tests;

use APIToolkit\API\Authentication\OAuth2\{EncryptedFileTokenStore, FileTokenStore, OAuth2Token, Psr16TokenStore};
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Tests\Contracts\Test;

class TokenStoreTest extends Test {
    private string $path;

    protected function setUp(): void {
        parent::setUp();
        $this->path = sys_get_temp_dir() . '/apitk-token-' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void {
        @unlink($this->path);
        parent::tearDown();
    }

    private function token(): OAuth2Token {
        return new OAuth2Token('access-abc', 'refresh-xyz', new DateTimeImmutable('+1 hour'), 'scope-a', 'Bearer');
    }

    public function test_file_store_round_trips_and_clears(): void {
        $store = new FileTokenStore($this->path);
        $this->assertNull($store->load());

        $store->save($this->token());
        $loaded = $store->load();
        $this->assertNotNull($loaded);
        $this->assertSame('access-abc', $loaded->getAccessToken());
        $this->assertSame('refresh-xyz', $loaded->getRefreshToken());

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->path)), -4));

        $store->clear();
        $this->assertNull($store->load());
    }

    public function test_encrypted_store_does_not_persist_plaintext_tokens(): void {
        $key = str_repeat("k", 32);
        $store = new EncryptedFileTokenStore($this->path, $key);

        $store->save($this->token());

        $onDisk = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('access-abc', $onDisk);
        $this->assertStringNotContainsString('refresh-xyz', $onDisk);

        $loaded = $store->load();
        $this->assertNotNull($loaded);
        $this->assertSame('access-abc', $loaded->getAccessToken());
    }

    public function test_encrypted_store_rejects_wrong_key(): void {
        (new EncryptedFileTokenStore($this->path, str_repeat('k', 32)))->save($this->token());

        $wrong = new EncryptedFileTokenStore($this->path, str_repeat('x', 32));
        $this->assertNull($wrong->load());
    }

    public function test_psr16_store_round_trips(): void {
        $cache = new class implements CacheInterface {
            /** @var array<string, mixed> */
            public array $data = [];

            public function get(string $key, mixed $default = null): mixed {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool {
                $this->data[$key] = $value;

                return true;
            }

            public function delete(string $key): bool {
                unset($this->data[$key]);

                return true;
            }

            public function clear(): bool {
                $this->data = [];

                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable {
                return [];
            }

            public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool {
                return true;
            }

            public function has(string $key): bool {
                return isset($this->data[$key]);
            }
        };

        $store = new Psr16TokenStore($cache, 'tenant-1:token');
        $this->assertNull($store->load());

        $store->save($this->token());
        $this->assertSame('access-abc', $store->load()?->getAccessToken());

        $store->clear();
        $this->assertNull($store->load());
    }
}
