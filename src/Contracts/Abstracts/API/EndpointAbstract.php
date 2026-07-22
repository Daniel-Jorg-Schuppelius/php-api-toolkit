<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EndpointAbstract.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts\API;

use APIToolkit\Contracts\Interfaces\API\{ApiClientInterface, EndpointInterface};
use APIToolkit\Exceptions\ApiException;
use ERRORToolkit\Traits\ErrorLog;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class EndpointAbstract implements EndpointInterface {
    use ErrorLog;

    protected ApiClientInterface $client;

    protected string $endpoint = '';
    protected string $endpointPrefix = '';
    protected string $endpointSuffix = '';

    public function __construct(ApiClientInterface $client, ?LoggerInterface $logger = null) {
        $this->client = $client;
        $this->initializeLogger($logger);
    }

    protected function getContents(array $queryParams = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $queryString = http_build_query($queryParams);
        $response = $this->client->get($urlPath . (empty($queryString) ? "" : "?{$queryString}"), $options);

        return $this->handleResponse($response, $statusCode);
    }

    protected function postContents(array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 201): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $options['json'] = $data;
        $response = $this->client->post($urlPath, $options);

        return $this->handleResponse($response, $statusCode);
    }

    protected function putContents(array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $options['json'] = $data;
        $response = $this->client->put($urlPath, $options);

        return $this->handleResponse($response, $statusCode);
    }

    protected function patchContents(array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $options['json'] = $data;
        $response = $this->client->patch($urlPath, $options);

        return $this->handleResponse($response, $statusCode);
    }

    protected function deleteContents(array $options = [], ?string $urlPath = null, int|array $statusCode = 204): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $response = $this->client->delete($urlPath, $options);

        return $this->handleResponse($response, $statusCode);
    }

    /**
     * Validate the response status and return the body.
     *
     * @param int|array<int, int> $expectedStatusCodes One or more acceptable status codes
     *                                                 (APIs may answer e.g. 200 or 201)
     * @return string Response body; the literal "success" for 204 (kept for BC)
     */
    protected function handleResponse(ResponseInterface $response, int|array $expectedStatusCodes): string {
        $statusCode = $response->getStatusCode();

        if (!in_array($statusCode, (array) $expectedStatusCodes, true)) {
            throw new ApiException('Unexpected response status code', $statusCode, $response, null);
        }

        if ($statusCode === 204) {
            return "success";
        }

        return $response->getBody()->getContents();
    }

    protected function getEndpointUrl(): string {
        $endpointPrefix = rtrim($this->endpointPrefix, '/');
        $endpoint = trim($this->endpoint, '/');
        $endpointSuffix = ltrim($this->endpointSuffix, '/');

        if (empty($endpoint)) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                "The endpoint must be set (Class: " . static::class . ")"
            );
        }

        // Nicht-leere Segmente in Reihenfolge zusammensetzen. Zuvor wurde ein
        // gesetzter Suffix verworfen, wenn kein Prefix gesetzt war.
        $segments = array_filter(
            [$endpointPrefix, $endpoint, $endpointSuffix],
            static fn (string $segment): bool => $segment !== ''
        );

        return implode('/', $segments);
    }
}
