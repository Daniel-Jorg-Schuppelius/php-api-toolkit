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
use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;
use APIToolkit\Exceptions\ApiException;
use APIToolkit\Exceptions\BadGatewayException;
use APIToolkit\Exceptions\BadRequestException;
use APIToolkit\Exceptions\ConflictException;
use APIToolkit\Exceptions\ForbiddenException;
use APIToolkit\Exceptions\GatewayTimeoutException;
use APIToolkit\Exceptions\InternalServerErrorException;
use APIToolkit\Exceptions\NotAcceptableException;
use APIToolkit\Exceptions\NotAllowedException;
use APIToolkit\Exceptions\NotFoundException;
use APIToolkit\Exceptions\PaymentRequiredException;
use APIToolkit\Exceptions\RequestTimeoutException;
use APIToolkit\Exceptions\ServiceUnavailableException;
use APIToolkit\Exceptions\TooManyRequestsException;
use APIToolkit\Exceptions\UnauthorizedException;
use APIToolkit\Exceptions\UnprocessableEntityException;
use APIToolkit\Exceptions\UnsupportedMediaTypeException;
use ERRORToolkit\Traits\ErrorLog;
use GuzzleHttp\Client as HttpClient;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class ClientAbstract implements ApiClientInterface {
    use ErrorLog;

    public const MIN_INTERVAL = 0.2;
    protected bool $sleepAfterRequest;
    protected float $lastRequestTime = 0.0;
    protected float $requestInterval = 0.25;

    protected int $maxRetries = 3;
    protected int $baseRetryDelay = 1;
    protected bool $exponentialBackoff = true;

    protected ?AuthenticationInterface $authentication = null;

    protected HttpClient $client;

    public function __construct(HttpClient $client, ?LoggerInterface $logger = null, bool $sleepAfterRequest = false) {
        $this->client = $client;
        $this->initializeLogger($logger);
        $this->sleepAfterRequest = $sleepAfterRequest;
    }

    public function setRequestInterval(float $requestInterval): void {
        if ($requestInterval < self::MIN_INTERVAL) {
            $this->logError('Request interval must be at least ' . self::MIN_INTERVAL . ' seconds');
            throw new InvalidArgumentException('Request interval must be at least ' . self::MIN_INTERVAL . ' seconds');
        }
        $this->requestInterval = $requestInterval;
    }

    public function getRequestInterval(): float {
        return $this->requestInterval;
    }

    public function setMaxRetries(int $maxRetries): void {
        if ($maxRetries < 1) {
            $this->logError('Max retries must be at least 1');
            throw new InvalidArgumentException('Max retries must be at least 1');
        }
        $this->maxRetries = $maxRetries;
    }

    public function getMaxRetries(): int {
        return $this->maxRetries;
    }

    public function setBaseRetryDelay(int $delay): void {
        if ($delay < 1) {
            $this->logError('Base retry delay must be at least 1 second');
            throw new InvalidArgumentException('Base retry delay must be at least 1 second');
        }
        $this->baseRetryDelay = $delay;
    }

    public function getBaseRetryDelay(): int {
        return $this->baseRetryDelay;
    }

    public function setExponentialBackoff(bool $enabled): void {
        $this->exponentialBackoff = $enabled;
    }

    public function isExponentialBackoffEnabled(): bool {
        return $this->exponentialBackoff;
    }

    public function setAuthentication(?AuthenticationInterface $authentication): void {
        $this->authentication = $authentication;
        if ($authentication !== null) {
            $this->logDebug("Authentication set: " . $authentication->getType());
        }
    }

    public function getAuthentication(): ?AuthenticationInterface {
        return $this->authentication;
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

    public function patch(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('PATCH', $uri, $options);
    }

    public function delete(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('DELETE', $uri, $options);
    }

    private function request(string $method, string $uri, array $options = []): ResponseInterface {
        $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
        $sleepTime = 0;

        if ($timeSinceLastRequest < $this->requestInterval) {
            $sleepTime = (int)(($this->requestInterval - $timeSinceLastRequest) * 1e6);
            usleep($sleepTime);
        }

        // Apply authentication headers if set
        if ($this->authentication !== null && $this->authentication->isValid()) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                $this->authentication->getAuthHeaders()
            );
        }

        $this->logDebug("Sending {$method} request to {$uri}" . ($sleepTime > 0 ? " (waited {$sleepTime} microseconds)" : ""), $options);

        $options['http_errors'] = false;
        $this->lastRequestTime = microtime(true);
        $response = $this->client->request($method, $uri, $options);

        if ($this->sleepAfterRequest) {
            // Sleep for 0.5 seconds after each request to avoid rate limiting
            usleep((int)(self::MIN_INTERVAL * 1e6));
        }

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        return $response;
    }

    protected function handleErrorResponse(ResponseInterface $response): never {
        $statusCode = $response->getStatusCode();

        match ($statusCode) {
            400 => throw new BadRequestException('Bad Request', 400, $response),
            401 => throw new UnauthorizedException('Unauthorized', 401, $response),
            402 => throw new PaymentRequiredException('Payment Required', 402, $response),
            403 => throw new ForbiddenException('Forbidden', 403, $response),
            404 => throw new NotFoundException('Resource not found', 404, $response),
            405 => throw new NotAllowedException('Not Allowed', 405, $response),
            406 => throw new NotAcceptableException('Not Acceptable', 406, $response),
            408 => throw new RequestTimeoutException('Request Timeout', 408, $response),
            409 => throw new ConflictException('Conflict', 409, $response),
            415 => throw new UnsupportedMediaTypeException('Unsupported Media Type', 415, $response),
            422 => throw new UnprocessableEntityException('Unprocessable Entity', 422, $response),
            429 => throw new TooManyRequestsException('Too Many Requests! Set a higher value for Client->requestInterval', 429, $response),
            500 => throw new InternalServerErrorException('Internal Server Error', 500, $response),
            502 => throw new BadGatewayException('Bad Gateway', 502, $response),
            503 => throw new ServiceUnavailableException('Service Unavailable', 503, $response),
            504 => throw new GatewayTimeoutException('Gateway Timeout', 504, $response),
            default => throw new ApiException('Unexpected response status code', $statusCode, $response, null, self::$logger),
        };
    }

    protected function requestWithRetry(string $method, string $uri, array $options = []): ResponseInterface {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->request($method, $uri, $options);
            } catch (TooManyRequestsException | ServiceUnavailableException | GatewayTimeoutException $e) {
                $attempt++;
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $delay = $this->calculateRetryDelay($attempt);
                $this->logWarning("Retrying request due to error: " . $e->getMessage() . " (attempt $attempt of $this->maxRetries, waiting {$delay}s)");

                sleep($delay);
            }
        }

        $this->logError("Max retries reached for {$method} request to {$uri}");
        throw new RuntimeException("Max retries reached for {$method} request to {$uri}");
    }

    protected function calculateRetryDelay(int $attempt): int {
        if ($this->exponentialBackoff) {
            return (int)($this->baseRetryDelay * pow(2, $attempt - 1));
        }
        return $this->baseRetryDelay;
    }
}
