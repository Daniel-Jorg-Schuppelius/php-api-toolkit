<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiKeyAuthentication.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class ApiKeyAuthentication implements AuthenticationInterface {
    protected string $apiKey;
    protected string $headerName;
    /** @var array<string, string> */
    protected array $additionalHeaders;

    /**
     * @param array<string, string> $additionalHeaders Optional additional headers to include
     */
    public function __construct(#[\SensitiveParameter] string $apiKey, string $headerName = 'X-API-Key', array $additionalHeaders = []) {
        $this->apiKey = $apiKey;
        $this->headerName = $headerName;
        $this->additionalHeaders = $additionalHeaders;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {
        return [
            'apiKey' => $this->apiKey === '' ? '' : '[redacted]',
            'headerName' => $this->headerName,
            'additionalHeaders' => $this->additionalHeaders,
        ];
    }

    public function getAuthHeaders(): array {
        return array_merge(
            [$this->headerName => $this->apiKey],
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
        return 'ApiKey';
    }

    public function isValid(): bool {
        return $this->apiKey !== '';
    }

    public function getApiKey(): string {
        return $this->apiKey;
    }

    public function setApiKey(#[\SensitiveParameter] string $apiKey): void {
        $this->apiKey = $apiKey;
    }

    public function getHeaderName(): string {
        return $this->headerName;
    }

    public function setHeaderName(string $headerName): void {
        $this->headerName = $headerName;
    }
}
