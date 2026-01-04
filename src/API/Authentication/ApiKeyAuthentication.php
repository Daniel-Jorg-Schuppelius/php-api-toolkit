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

    public function __construct(string $apiKey, string $headerName = 'X-API-Key') {
        $this->apiKey = $apiKey;
        $this->headerName = $headerName;
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
        return !empty($this->apiKey);
    }

    public function getApiKey(): string {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void {
        $this->apiKey = $apiKey;
    }

    public function getHeaderName(): string {
        return $this->headerName;
    }

    public function setHeaderName(string $headerName): void {
        $this->headerName = $headerName;
    }
}
