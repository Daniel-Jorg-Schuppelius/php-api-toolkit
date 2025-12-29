<?php
/*
 * Created on   : Sun Dec 29 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuthenticationInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

interface AuthenticationInterface {
    /**
     * Get the authentication headers to be added to requests
     *
     * @return array<string, string>
     */
    public function getAuthHeaders(): array;

    /**
     * Get the authentication type identifier
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Check if the authentication credentials are valid/set
     *
     * @return bool
     */
    public function isValid(): bool;
}
