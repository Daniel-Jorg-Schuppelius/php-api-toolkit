<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BearerAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class BearerAuthentication implements AuthenticationInterface {
    protected string $token;
    /** @var array<string, string> */
    protected array $additionalHeaders = [];

    /**
     * @param string $token The bearer token
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     */
    public function __construct(string $token, array $additionalHeaders = []) {
        $this->token = $token;
        $this->additionalHeaders = $additionalHeaders;
    }

    public function getAuthHeaders(): array {
        return array_merge(
            ['Authorization' => 'Bearer ' . $this->token],
            $this->additionalHeaders
        );
    }

    public function getType(): string {
        return 'Bearer';
    }

    public function isValid(): bool {
        return !empty($this->token);
    }

    public function getToken(): string {
        return $this->token;
    }

    public function setToken(string $token): void {
        $this->token = $token;
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

    public function addHeader(string $name, string $value): void {
        $this->additionalHeaders[$name] = $value;
    }

    public function removeHeader(string $name): void {
        unset($this->additionalHeaders[$name]);
    }
}
