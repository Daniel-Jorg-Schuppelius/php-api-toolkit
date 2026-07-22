<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BasicAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class BasicAuthentication implements AuthenticationInterface {
    protected string $username;
    protected string $password;
    /** @var array<string, string> */
    protected array $additionalHeaders;

    /**
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     */
    public function __construct(string $username, #[\SensitiveParameter] string $password, array $additionalHeaders = []) {
        $this->username = $username;
        $this->password = $password;
        $this->additionalHeaders = $additionalHeaders;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {
        return [
            'username' => $this->username,
            'password' => $this->password === '' ? '' : '[redacted]',
            'additionalHeaders' => $this->additionalHeaders,
        ];
    }

    public function getAuthHeaders(): array {
        $credentials = base64_encode($this->username . ':' . $this->password);

        return array_merge(
            ['Authorization' => 'Basic ' . $credentials],
            $this->additionalHeaders
        );
    }

    /**
     * @return array<string, string>
     */
    public function getAdditionalHeaders(): array {
        return $this->additionalHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setAdditionalHeaders(array $headers): void {
        $this->additionalHeaders = $headers;
    }

    public function getType(): string {
        return 'Basic';
    }

    public function isValid(): bool {
        return $this->username !== '';
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function setUsername(string $username): void {
        $this->username = $username;
    }

    public function setPassword(#[\SensitiveParameter] string $password): void {
        $this->password = $password;
    }
}
