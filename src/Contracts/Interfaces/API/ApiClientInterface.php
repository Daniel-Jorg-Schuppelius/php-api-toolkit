<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApiClientInterface.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Interfaces\API;

use Psr\Http\Message\ResponseInterface;

interface ApiClientInterface {
    public function get(string $uri, array $options = []): ResponseInterface;
    public function post(string $uri, array $options = []): ResponseInterface;
    public function put(string $uri, array $options = []): ResponseInterface;
    public function patch(string $uri, array $options = []): ResponseInterface;
    public function delete(string $uri, array $options = []): ResponseInterface;
}
