<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileTokenStore.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use APIToolkit\Contracts\Interfaces\API\OAuth2TokenStoreInterface;
use RuntimeException;

/**
 * Persists an OAuth2 token as JSON in a single file.
 *
 * The file is created with 0600 permissions and written atomically (temp file
 * + rename) so a concurrent reader never sees a half-written token. Suitable
 * for CLI tools and single-host machine-to-machine clients; for multi-host or
 * multi-tenant setups use an application-side store or {@see Psr16TokenStore}.
 */
class FileTokenStore implements OAuth2TokenStoreInterface {
    protected string $path;

    public function __construct(string $path) {
        if ($path === '') {
            throw new RuntimeException('Token store path must not be empty');
        }
        $this->path = $path;
    }

    public function load(): ?OAuth2Token {
        if (!is_file($this->path)) {
            return null;
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }

        return OAuth2Token::fromArray($data);
    }

    public function save(OAuth2Token $token): void {
        $this->writeAtomic((string) json_encode($token->toArray()));
    }

    public function clear(): void {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }

    protected function writeAtomic(string $contents): void {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create token store directory: {$dir}");
        }

        $tmp = @tempnam($dir, '.tok');
        if ($tmp === false) {
            throw new RuntimeException("Cannot create temp file in: {$dir}");
        }

        @chmod($tmp, 0600);
        if (@file_put_contents($tmp, $contents, LOCK_EX) === false || !@rename($tmp, $this->path)) {
            @unlink($tmp);
            throw new RuntimeException("Cannot write token store file: {$this->path}");
        }

        @chmod($this->path, 0600);
    }
}
