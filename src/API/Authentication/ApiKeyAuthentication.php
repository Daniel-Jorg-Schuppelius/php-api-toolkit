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

    public function __construct(#[\SensitiveParameter] string $apiKey, string $headerName = 'X-API-Key') {
        $this->apiKey = $apiKey;
        $this->headerName = $headerName;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array {
        return [
            'apiKey' => $this->apiKey === '' ? '' : '[redacted]',
            'headerName' => $this->headerName,
        ];
    }

    public function getAuthHeaders(): array {
        return [
            $this->headerName => $this->apiKey,
        ];
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
