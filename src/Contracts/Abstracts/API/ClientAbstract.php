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

    /** @var array<string, string> */
    protected array $defaultHeaders = [];

    protected bool $verifySSL = true;

    protected float $timeout = 30.0;
    protected float $connectTimeout = 10.0;

    protected ?string $proxy = null;

    protected ?string $userAgent = null;

    /** @var array<string, mixed> */
    protected array $defaultQueryParams = [];

    protected string $baseUrl;

    protected HttpClient $client;

    /**
     * Create a new API client
     *
     * @param string $baseUrl The base URL for all API requests (e.g., 'https://api.example.com')
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param bool $sleepAfterRequest Whether to sleep after each request
     * @param HttpClient|null $httpClient Optional pre-configured Guzzle client (for advanced use cases)
     */
    public function __construct(string $baseUrl, ?LoggerInterface $logger = null, bool $sleepAfterRequest = false, ?HttpClient $httpClient = null) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->initializeLogger($logger);
        $this->sleepAfterRequest = $sleepAfterRequest;

        if ($httpClient !== null) {
            $this->client = $httpClient;
        } else {
            $this->client = new HttpClient(['base_uri' => $this->baseUrl]);
        }
    }

    /**
     * Get the base URL for API requests
     */
    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    /**
     * Set a new base URL and recreate the HTTP client
     *
     * @param string $baseUrl The new base URL
     */
    public function setBaseUrl(string $baseUrl): void {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new HttpClient(['base_uri' => $this->baseUrl]);
        $this->logDebug("Base URL changed to: {$this->baseUrl}");
    }

    public function setRequestInterval(float $requestInterval): void {
        if ($requestInterval < self::MIN_INTERVAL) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Request interval must be at least ' . self::MIN_INTERVAL . ' seconds'
            );
        }
        $this->requestInterval = $requestInterval;
    }

    public function getRequestInterval(): float {
        return $this->requestInterval;
    }

    public function setMaxRetries(int $maxRetries): void {
        if ($maxRetries < 1) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Max retries must be at least 1'
            );
        }
        $this->maxRetries = $maxRetries;
    }

    public function getMaxRetries(): int {
        return $this->maxRetries;
    }

    public function setBaseRetryDelay(int $delay): void {
        if ($delay < 1) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Base retry delay must be at least 1 second'
            );
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

    /**
     * Set default headers to be included in every request
     *
     * @param array<string, string> $headers
     */
    public function setDefaultHeaders(array $headers): void {
        $this->defaultHeaders = $headers;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array {
        return $this->defaultHeaders;
    }

    public function addDefaultHeader(string $name, string $value): void {
        $this->defaultHeaders[$name] = $value;
    }

    public function removeDefaultHeader(string $name): void {
        unset($this->defaultHeaders[$name]);
    }

    /**
     * Enable or disable SSL certificate verification
     *
     * WARNING: Disabling SSL verification is insecure and should only be used
     * in development environments or with self-signed certificates.
     *
     * @param bool $verify Whether to verify SSL certificates
     */
    public function setVerifySSL(bool $verify): void {
        $this->verifySSL = $verify;
        if (!$verify) {
            $this->logWarning('SSL verification has been disabled. This is insecure!');
        }
    }

    public function isSSLVerificationEnabled(): bool {
        return $this->verifySSL;
    }

    /**
     * Set the request timeout in seconds
     *
     * @param float $timeout Timeout in seconds (0 = no timeout)
     */
    public function setTimeout(float $timeout): void {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Timeout must be >= 0');
        }
        $this->timeout = $timeout;
    }

    public function getTimeout(): float {
        return $this->timeout;
    }

    /**
     * Set the connection timeout in seconds
     *
     * @param float $timeout Connection timeout in seconds (0 = no timeout)
     */
    public function setConnectTimeout(float $timeout): void {
        if ($timeout < 0) {
            throw new InvalidArgumentException('Connect timeout must be >= 0');
        }
        $this->connectTimeout = $timeout;
    }

    public function getConnectTimeout(): float {
        return $this->connectTimeout;
    }

    /**
     * Set a proxy server for all requests
     *
     * @param string|null $proxy Proxy URL (e.g., 'http://proxy:8080') or null to disable
     */
    public function setProxy(?string $proxy): void {
        $this->proxy = $proxy;
        if ($proxy !== null) {
            $this->logDebug("Proxy configured: {$proxy}");
        }
    }

    public function getProxy(): ?string {
        return $this->proxy;
    }

    /**
     * Set the User-Agent header for all requests
     *
     * @param string|null $userAgent User-Agent string or null to use default
     */
    public function setUserAgent(?string $userAgent): void {
        $this->userAgent = $userAgent;
    }

    public function getUserAgent(): ?string {
        return $this->userAgent;
    }

    /**
     * Set default query parameters to be included in every request
     *
     * @param array<string, mixed> $params
     */
    public function setDefaultQueryParams(array $params): void {
        $this->defaultQueryParams = $params;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultQueryParams(): array {
        return $this->defaultQueryParams;
    }

    public function addDefaultQueryParam(string $name, mixed $value): void {
        $this->defaultQueryParams[$name] = $value;
    }

    public function removeDefaultQueryParam(string $name): void {
        unset($this->defaultQueryParams[$name]);
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

        // Apply default headers first
        if (!empty($this->defaultHeaders)) {
            $options['headers'] = array_merge(
                $this->defaultHeaders,
                $options['headers'] ?? []
            );
        }

        // Apply authentication headers if set (these override default headers)
        if ($this->authentication !== null && $this->authentication->isValid()) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                $this->authentication->getAuthHeaders()
            );
        }

        $this->logDebug("Sending {$method} request to {$uri}" . ($sleepTime > 0 ? " (waited {$sleepTime} microseconds)" : ""), $options);

        $options['http_errors'] = false;
        $options['verify'] = $this->verifySSL;
        $options['timeout'] = $this->timeout;
        $options['connect_timeout'] = $this->connectTimeout;

        // Apply proxy if set
        if ($this->proxy !== null) {
            $options['proxy'] = $this->proxy;
        }

        // Apply User-Agent if set
        if ($this->userAgent !== null) {
            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                ['User-Agent' => $this->userAgent]
            );
        }

        // Apply default query parameters
        if (!empty($this->defaultQueryParams)) {
            $options['query'] = array_merge(
                $this->defaultQueryParams,
                $options['query'] ?? []
            );
        }

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
                    self::logException($e);
                    throw $e;
                }

                $delay = $this->calculateRetryDelay($attempt);
                $this->logWarning("Retrying request due to error: {message} (attempt {attempt} of {maxRetries}, waiting {delay}s)", [
                    'message' => $e->getMessage(),
                    'attempt' => $attempt,
                    'maxRetries' => $this->maxRetries,
                    'delay' => $delay,
                ]);

                sleep($delay);
            }
        }

        self::logErrorAndThrow(
            RuntimeException::class,
            "Max retries reached for {$method} request to {$uri}"
        );
    }

    protected function calculateRetryDelay(int $attempt): int {
        if ($this->exponentialBackoff) {
            return (int)($this->baseRetryDelay * pow(2, $attempt - 1));
        }
        return $this->baseRetryDelay;
    }
}
