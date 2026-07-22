<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PendingRequest.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API;

use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Chainable, discoverable request builder that accumulates Guzzle options and
 * lowers to the same options the client already understands.
 *
 *   $client->pending()
 *       ->withHeader('Accept', 'application/json')
 *       ->withQuery(['page' => 2])
 *       ->withIdempotencyKey($key)
 *       ->post('/charges');
 */
class PendingRequest {
    private ApiClientInterface $client;
    /** @var array<string, mixed> */
    private array $options = [];

    public function __construct(ApiClientInterface $client) {
        $this->client = $client;
    }

    public function withHeader(string $name, string $value): self {
        $this->options['headers'][$name] = $value;

        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self {
        $this->options['headers'] = array_merge($this->options['headers'] ?? [], $headers);

        return $this;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQuery(array $query): self {
        $this->options['query'] = array_merge($this->options['query'] ?? [], $query);

        return $this;
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function withJson(array $data): self {
        $this->options['json'] = $data;

        return $this;
    }

    /**
     * @param array<string, mixed> $formParams
     */
    public function withFormParams(array $formParams): self {
        $this->options['form_params'] = $formParams;

        return $this;
    }

    public function withBody(string $body): self {
        $this->options['body'] = $body;

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $parts Guzzle multipart parts
     */
    public function asMultipart(array $parts): self {
        $this->options['multipart'] = $parts;

        return $this;
    }

    public function withIdempotencyKey(string $key): self {
        $this->options['idempotency_key'] = $key;

        return $this;
    }

    public function timeout(float $seconds): self {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    /**
     * Escape hatch for any other Guzzle request option.
     */
    public function withOption(string $key, mixed $value): self {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array {
        return $this->options;
    }

    public function get(string $uri): ResponseInterface {
        return $this->client->get($uri, $this->options);
    }

    public function post(string $uri): ResponseInterface {
        return $this->client->post($uri, $this->options);
    }

    public function put(string $uri): ResponseInterface {
        return $this->client->put($uri, $this->options);
    }

    public function patch(string $uri): ResponseInterface {
        return $this->client->patch($uri, $this->options);
    }

    public function delete(string $uri): ResponseInterface {
        return $this->client->delete($uri, $this->options);
    }
}
