<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EncryptedFileTokenStore.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication\OAuth2;

use CommonToolkit\Helper\Data\CryptoHelper;
use RuntimeException;
use Throwable;

/**
 * File token store that encrypts the token at rest with AES-256-GCM.
 *
 * The token JSON is authenticated-encrypted (via CommonToolkit\CryptoHelper)
 * under a caller-supplied 32-byte key before it is written, so a leaked token
 * file does not expose the access/refresh tokens. A tampered or wrong-key file
 * fails the authentication tag and is treated as "no token".
 */
class EncryptedFileTokenStore extends FileTokenStore {
    private string $key;

    /**
     * @param string $path Storage path
     * @param string $key  32-byte encryption key (AES-256). Use
     *                     CryptoHelper::generateKey(32) and keep it in your
     *                     secret store; a base64 key is decoded automatically.
     */
    public function __construct(string $path, #[\SensitiveParameter] string $key) {
        parent::__construct($path);

        $binaryKey = self::normalizeKey($key);
        if (strlen($binaryKey) !== 32) {
            throw new RuntimeException('Encryption key must be 32 bytes (AES-256)');
        }
        $this->key = $binaryKey;
    }

    public function load(): ?OAuth2Token {
        if (!is_file($this->path)) {
            return null;
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $envelope = json_decode($raw, true);
        if (!is_array($envelope)) {
            return null;
        }

        try {
            $plaintext = CryptoHelper::decrypt($envelope, $this->key);
        } catch (Throwable) {
            // Wrong key or tampered file → treat as no usable token.
            return null;
        }

        $data = json_decode($plaintext, true);
        if (!is_array($data)) {
            return null;
        }

        return OAuth2Token::fromArray($data);
    }

    public function save(OAuth2Token $token): void {
        $envelope = CryptoHelper::encrypt((string) json_encode($token->toArray()), $this->key);
        $this->writeAtomic((string) json_encode($envelope));
    }

    private static function normalizeKey(string $key): string {
        if (strlen($key) === 32) {
            return $key;
        }

        $decoded = base64_decode($key, true);

        return $decoded !== false && strlen($decoded) === 32 ? $decoded : $key;
    }
}
