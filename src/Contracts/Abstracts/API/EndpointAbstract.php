<?php

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts\API;

use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use APIToolkit\Contracts\Interfaces\API\EndpointInterface;
use APIToolkit\Exceptions\ApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

abstract class EndpointAbstract implements EndpointInterface {
    protected ?LoggerInterface $logger;

    protected ApiClientInterface $client;

    protected string $endpointPrefix = '';
    protected string $endpoint = '';

    public function __construct(ApiClientInterface $client, ?LoggerInterface $logger = null) {
        $this->client = $client;
        $this->logger = $logger;
    }

    protected function getContents(array $queryParams = [], array $options = [], string $urlPath = null, int $statusCode = 200): string {
        if (is_null($urlPath)) {
            $urlPath = $this->getEndpointUrl();
        }
        $queryString = http_build_query($queryParams);
        $response = $this->client->get($urlPath . (empty($queryString) ? "" : "?{$queryString}"), $options);

        return $this->handleResponse($response, $statusCode);;
    }

    protected function handleResponse(ResponseInterface $response, int $expectedStatusCode): string {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== $expectedStatusCode) {
            throw new ApiException('Unexpected response status code', $statusCode, $response);
        }

        if ($statusCode === 204) {
            return "success";
        }

        return $response->getBody()->getContents();
    }

    protected function getEndpointUrl(): string {
        $endpointPrefix = rtrim($this->endpointPrefix, '/');
        $endpoint = ltrim($this->endpoint, '/');

        if (empty($endpoint)) {
            throw new \InvalidArgumentException("The endpoint must be set.");
        }

        if (!empty($endpointPrefix)) {
            $result = "{$endpointPrefix}/{$endpoint}";
        } else {
            $result = $endpoint;
        }

        return $result;
    }
}
