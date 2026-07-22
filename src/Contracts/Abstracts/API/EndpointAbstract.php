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
use APIToolkit\Contracts\Interfaces\NamedEntityInterface;
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
     * GET the endpoint and decode the JSON body into an array (for ad-hoc
     * mapping or paginators).
     *
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $options
     * @return array<int|string, mixed>
     */
    protected function getArray(array $queryParams = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): array {
        $decoded = json_decode($this->getContents($queryParams, $options, $urlPath, $statusCode), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * GET the endpoint and hydrate the JSON response into an entity/collection
     * via its static fromJson(), removing per-SDK json_decode + mapping.
     *
     * @template T of NamedEntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $options
     * @return T
     */
    protected function getEntity(string $entityClass, array $queryParams = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): NamedEntityInterface {
        return $entityClass::fromJson($this->getContents($queryParams, $options, $urlPath, $statusCode));
    }

    /**
     * POST $data and hydrate the response into an entity/collection.
     *
     * @template T of NamedEntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return T
     */
    protected function postEntity(string $entityClass, array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 201): NamedEntityInterface {
        return $entityClass::fromJson($this->postContents($data, $options, $urlPath, $statusCode));
    }

    /**
     * PUT $data and hydrate the response into an entity/collection.
     *
     * @template T of NamedEntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return T
     */
    protected function putEntity(string $entityClass, array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): NamedEntityInterface {
        return $entityClass::fromJson($this->putContents($data, $options, $urlPath, $statusCode));
    }

    /**
     * PATCH $data and hydrate the response into an entity/collection.
     *
     * @template T of NamedEntityInterface
     * @param class-string<T> $entityClass
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     * @return T
     */
    protected function patchEntity(string $entityClass, array $data = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 200): NamedEntityInterface {
        return $entityClass::fromJson($this->patchContents($data, $options, $urlPath, $statusCode));
    }

    /**
     * POST multipart/form-data (simple fields + file parts) — for document and
     * attachment uploads that cannot use the JSON helpers.
     *
     * @param array<string, scalar> $fields Simple form fields (name => value)
     * @param array<int, array<string, mixed>> $files Guzzle multipart parts (name/contents[/filename/headers])
     * @param array<string, mixed> $options
     */
    protected function postMultipart(array $fields = [], array $files = [], array $options = [], ?string $urlPath = null, int|array $statusCode = 201): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }

        $multipart = [];
        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => (string) $name, 'contents' => (string) $value];
        }
        foreach ($files as $part) {
            $multipart[] = $part;
        }

        $options['multipart'] = $multipart;
        $response = $this->client->post($urlPath, $options);

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
