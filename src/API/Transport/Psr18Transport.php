<?php
/*
 * Created on   : Wed Jul 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Psr18Transport.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\API\Transport;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface, ResponseInterface, StreamFactoryInterface};

/**
 * Adapts the toolkit's Guzzle-style option array onto a PSR-18 client and
 * PSR-17 factories, so any PSR-18 transport can back the API client (swappable
 * transports, framework HTTP clients, easier mocking).
 *
 * Supported options: headers, query, json, form_params and a raw string body.
 * PSR-18 has no notion of per-request timeout/proxy/TLS-verify or multipart, so
 * those Guzzle options are ignored here — keep using an injected Guzzle client
 * when you need them.
 */
class Psr18Transport {
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private string $baseUri;

    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri = ''
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface {
        $request = $this->requestFactory->createRequest($method, $this->resolveUri($uri, $options));

        foreach (($options['headers'] ?? []) as $name => $value) {
            $request = $request->withHeader((string) $name, is_array($value) ? $value : (string) $value);
        }

        $request = $this->applyBody($request, $options);

        return $this->client->sendRequest($request);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveUri(string $uri, array $options): string {
        $absolute = preg_match('~^https?://~i', $uri) === 1 ? $uri : $this->baseUri . '/' . ltrim($uri, '/');

        $query = $options['query'] ?? null;
        if (is_array($query) && $query !== []) {
            $absolute .= (str_contains($absolute, '?') ? '&' : '?') . http_build_query($query);
        } elseif (is_string($query) && $query !== '') {
            $absolute .= (str_contains($absolute, '?') ? '&' : '?') . $query;
        }

        return $absolute;
    }

    /**
     * @param array<string, mixed> $options
     * @param \Psr\Http\Message\RequestInterface $request
     */
    private function applyBody($request, array $options) {
        if (array_key_exists('json', $options)) {
            $body = json_encode($options['json']);
            if ($body === false) {
                throw new InvalidArgumentException('Could not JSON-encode the request payload');
            }
            if (!$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/json');
            }

            return $request->withBody($this->streamFactory->createStream($body));
        }

        if (isset($options['form_params']) && is_array($options['form_params'])) {
            if (!$request->hasHeader('Content-Type')) {
                $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
            }

            return $request->withBody($this->streamFactory->createStream(http_build_query($options['form_params'])));
        }

        if (isset($options['body']) && is_string($options['body'])) {
            return $request->withBody($this->streamFactory->createStream($options['body']));
        }

        return $request;
    }
}
