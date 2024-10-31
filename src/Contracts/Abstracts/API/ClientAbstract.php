<?php
/*
 * Created on   : Sun Oct 06 2024
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClientAbstract.php
 * License      : MIT License
 * License Uri  : https://opensource.org/license/mit
 */

declare(strict_types=1);

namespace APIToolkit\Contracts\Abstracts\API;

use APIToolkit\Contracts\Interfaces\API\ApiClientInterface;
use APIToolkit\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client as HttpClient;
use APIToolkit\Exceptions\BadRequestException;
use APIToolkit\Exceptions\ConflictException;
use APIToolkit\Exceptions\ForbiddenException;
use APIToolkit\Exceptions\NotAcceptableException;
use APIToolkit\Exceptions\NotAllowedException;
use APIToolkit\Exceptions\NotFoundException;
use APIToolkit\Exceptions\PaymentRequiredException;
use APIToolkit\Exceptions\TooManyRequestsException;
use APIToolkit\Exceptions\UnauthorizedException;
use APIToolkit\Exceptions\UnsupportedMediaTypeException;
use APIToolkit\Traits\ErrorLog;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class ClientAbstract implements ApiClientInterface {
    use ErrorLog;

    public const MIN_INTERVAL = 0.2;
    protected bool $sleepAfterRequest;
    protected float $lastRequestTime = 0.0;
    protected float $requestInterval = 0.25;

    protected HttpClient $client;

    public function __construct(HttpClient $client, ?LoggerInterface $logger = null, bool $sleepAfterRequest = false) {
        $this->client = $client;
        $this->logger = $logger;
        $this->sleepAfterRequest = $sleepAfterRequest;
    }

    public function setRequestInterval(float $requestInterval): void {
        if ($requestInterval < ClientAbstract::MIN_INTERVAL) {
            $this->logError('Request interval must be at least ' . ClientAbstract::MIN_INTERVAL . ' seconds');
            throw new InvalidArgumentException('Request interval must be at least ' . ClientAbstract::MIN_INTERVAL . ' seconds');
        }
        $this->requestInterval = $requestInterval;
    }

    public function getRequestInterval(): float {
        return $this->requestInterval;
    }

    public function get(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('POST', $uri, $options);
    }

    public function put(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('PUT', $uri, $options);
    }

    public function delete(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('DELETE', $uri, $options);
    }

    private function request(string $method, string $uri, array $options = []): ResponseInterface {
        $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
        $microsecondsToSleep = 0;

        if ($timeSinceLastRequest < $this->requestInterval) {
            usleep((int)(($this->requestInterval - $timeSinceLastRequest) * 1e6));
        }

        $this->logDebug("Sending {$method} request to {$uri} (waiting {$microsecondsToSleep} microseconds to execute)", $options);

        $options['http_errors'] = false;
        $this->lastRequestTime = microtime(true);
        $response = $this->client->request($method, $uri, $options);

        if ($this->sleepAfterRequest) {
            // Sleep for 0.5 seconds after each request to avoid rate limiting
            usleep((int)(ClientAbstract::MIN_INTERVAL * 1e6));
        }

        if ($response->getStatusCode() >= 400) {
            switch ($response->getStatusCode()) {
                case 400:
                    throw new BadRequestException('Bad Request', 400, $response);
                case 401:
                    throw new UnauthorizedException('Unauthorized', 401, $response);
                case 402:
                    throw new PaymentRequiredException('Payment Required', 402, $response);
                case 403:
                    throw new ForbiddenException('Forbidden', 403, $response);
                case 404:
                    throw new NotFoundException('Resource not found', 404, $response);
                case 405:
                    throw new NotAllowedException('Not Allowed', 405, $response);
                case 406:
                    throw new NotAcceptableException('Not Acceptable', 406, $response);
                case 409:
                    throw new ConflictException('Conflict', 409, $response);
                case 415:
                    throw new UnsupportedMediaTypeException('Unsupported Media Type', 415, $response);
                case 429:
                    throw new TooManyRequestsException('Too Many Requests! Set a higher value for Client->requestInterval', 429, $response);
                default:
                    throw new ApiException('Unexpected response status code', $response->getStatusCode(), $response, null, $this->logger);
            }
        }

        return $response;
    }

    protected function requestWithRetry(string $method, string $uri, array $options = [], int $maxRetries = 1, int $retryDelay = 1): ResponseInterface {
        $attempt = 0;

        if ($maxRetries < 1) {
            $this->logError("Max retries must be at least 1");
            throw new InvalidArgumentException("Max retries must be at least 1");
        } elseif ($retryDelay < 1) {
            $this->logError("Retry delay must be at least 1 second");
            throw new InvalidArgumentException("Retry delay must be at least 1 second");
        }

        while ($attempt < $maxRetries) {
            try {
                return $this->request($method, $uri, $options);
            } catch (TooManyRequestsException | ApiException $e) {
                $attempt++;
                if ($attempt >= $maxRetries) {
                    throw $e; // after the last attempt, the error is forwarded
                }

                $this->logWarning("Retrying request due to error: " . $e->getMessage() . " (attempt $attempt of $maxRetries)");

                sleep($retryDelay);
            }
        }

        $this->logError("Max retries reached for {$method} request to {$uri}");
        throw new RuntimeException("Max retries reached for {$method} request to {$uri}");
    }
}
