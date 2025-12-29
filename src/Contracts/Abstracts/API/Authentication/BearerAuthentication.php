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

namespace APIToolkit\Contracts\Abstracts\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class BearerAuthentication implements AuthenticationInterface {
    protected string $token;

    public function __construct(string $token) {
        $this->token = $token;
    }

    public function getAuthHeaders(): array {
        return [
            'Authorization' => 'Bearer ' . $this->token,
        ];
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
}
