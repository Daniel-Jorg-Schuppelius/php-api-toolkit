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

namespace APIToolkit\Contracts\Abstracts\API\Authentication;

use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class BasicAuthentication implements AuthenticationInterface {
    protected string $username;
    protected string $password;

    public function __construct(string $username, string $password) {
        $this->username = $username;
        $this->password = $password;
    }

    public function getAuthHeaders(): array {
        $credentials = base64_encode($this->username . ':' . $this->password);
        return [
            'Authorization' => 'Basic ' . $credentials,
        ];
    }

    public function getType(): string {
        return 'Basic';
    }

    public function isValid(): bool {
        return !empty($this->username);
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function setUsername(string $username): void {
        $this->username = $username;
    }

    public function setPassword(string $password): void {
        $this->password = $password;
    }
}
