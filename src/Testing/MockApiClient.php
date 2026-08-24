<?php
/*
 * Created on   : Wed Jul 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MockApiClient.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Testing;

use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Client-Attrappe für Endpoint-Tests: registrierte Antworten je Methode und
 * URI-Muster, dazu ein Protokoll der abgesetzten Aufrufe.
 *
 * Deckt die Fachlogik der Endpoints ab, **nicht** die Transportschicht — für
 * URL-Bau, Header, Auth und Retry gehört ein Test mit injiziertem
 * Guzzle-MockHandler gegen den echten Client daneben.
 */
class MockApiClient implements ApiClientInterface {
    /** @var array<string, array{statusCode: int, body: string, headers: array<string, mixed>}> */
    private array $responses = [];

    /** @var array<array{method: string, uri: string, options: array<string, mixed>}> */
    private array $requestLog = [];

    private int $defaultStatusCode = 200;

    private string $defaultBody = '{}';

    /**
     * Registriert eine Antwort für Methode und URI-Muster.
     *
     * @param string $method HTTP-Methode (GET, POST, PUT, DELETE, PATCH) oder '*' für jede
     * @param string $uriPattern URI-Muster, optional mit Platzhaltern (*)
     * @param array<string, mixed> $headers
     */
    public function addResponse(string $method, string $uriPattern, int $statusCode, string $body, array $headers = []): self {
        $key = strtoupper($method) . ':' . $uriPattern;
        $this->responses[$key] = [
            'statusCode' => $statusCode,
            'body' => $body,
            'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
        ];

        return $this;
    }

    /** Antwort für URIs ohne passendes Muster. */
    public function setDefaultResponse(int $statusCode, string $body): self {
        $this->defaultStatusCode = $statusCode;
        $this->defaultBody = $body;

        return $this;
    }

    /**
     * @return array<array{method: string, uri: string, options: array<string, mixed>}>
     */
    public function getRequestLog(): array {
        return $this->requestLog;
    }

    /**
     * @return array{method: string, uri: string, options: array<string, mixed>}|null
     */
    public function getLastRequest(): ?array {
        return $this->requestLog[count($this->requestLog) - 1] ?? null;
    }

    public function clearRequestLog(): void {
        $this->requestLog = [];
    }

    public function clearResponses(): void {
        $this->responses = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $uri, array $options = []): ResponseInterface {
        return $this->dispatch('GET', $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $uri, array $options = []): ResponseInterface {
        return $this->dispatch('POST', $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $uri, array $options = []): ResponseInterface {
        return $this->dispatch('PUT', $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function patch(string $uri, array $options = []): ResponseInterface {
        return $this->dispatch('PATCH', $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $uri, array $options = []): ResponseInterface {
        return $this->dispatch('DELETE', $uri, $options);
    }

    /**
     * Beliebige HTTP-Methode (auch WebDAV-Verben wie PROPFIND/MKCOL/REPORT) —
     * Gegenstück zu ClientAbstract::request() für Endpoint-Tests.
     *
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface {
        return $this->dispatch(strtoupper(trim($method)), $uri, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function dispatch(string $method, string $uri, array $options): ResponseInterface {
        $this->requestLog[] = [
            'method' => $method,
            'uri' => $uri,
            'options' => $options,
        ];

        $method = strtoupper($method);

        // Erst methodengenaue Muster, dann Platzhalter-Methoden — sonst würde
        // ein '*'-Eintrag eine spezifischere Registrierung verdecken.
        foreach ([$method, '*'] as $wanted) {
            foreach ($this->responses as $pattern => $response) {
                [$patternMethod, $patternUri] = explode(':', $pattern, 2);
                if ($patternMethod === $wanted && $this->matchUri($uri, $patternUri)) {
                    return new Response($response['statusCode'], $response['headers'], $response['body']);
                }
            }
        }

        return new Response($this->defaultStatusCode, ['Content-Type' => 'application/json'], $this->defaultBody);
    }

    private function matchUri(string $uri, string $pattern): bool {
        if ($uri === $pattern) {
            return true;
        }

        $regex = str_replace(['/', '*'], ['\/', '.*'], $pattern);

        return (bool) preg_match('/^' . $regex . '$/', $uri);
    }
}
