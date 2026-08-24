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

use APIToolkit\API\RateLimit;
use APIToolkit\Contracts\Interfaces\API\{ApiClientInterface, AuthenticationInterface, RefreshableAuthenticationInterface, RequestAwareAuthenticationInterface};
use APIToolkit\Exceptions\{ApiException, BadGatewayException, BadRequestException, ConflictException, ForbiddenException, GatewayTimeoutException, InternalServerErrorException, NotAcceptableException, NotAllowedException, NotFoundException, PaymentRequiredException, RequestTimeoutException, ServiceUnavailableException, TooManyRequestsException, UnauthorizedException, UnprocessableEntityException, UnsupportedMediaTypeException};
use ERRORToolkit\Traits\ErrorLog;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class ClientAbstract implements ApiClientInterface {
    use ErrorLog;

    public const MIN_INTERVAL = 0.2;

    /** Header names (lowercase) whose values are redacted before logging. */
    protected const SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'api-key',
        'x-auth-token',
    ];

    /** Query/form/json parameter names (lowercase) redacted before logging. */
    protected const SENSITIVE_PARAMS = [
        'access_token',
        'api_key',
        'api_token',
        'apikey',
        'assertion',
        'auth_token',
        'client_assertion',
        'client_secret',
        'code',
        'code_verifier',
        'password',
        'private_key',
        'refresh_token',
        'secret',
        'signature',
        'token',
    ];

    private const REDACTED = '[redacted]';

    /**
     * Per RFC als idempotent (oder safe) definierte HTTP-/WebDAV-Methoden —
     * nur diese dürfen nach einem gesendeten Request wiederholt werden.
     *
     * Quellen: RFC 7231 §4.2 (GET/HEAD/OPTIONS/TRACE safe, PUT/DELETE
     * idempotent), RFC 4918 §9 (PROPFIND/PROPPATCH/MKCOL/COPY/MOVE/UNLOCK —
     * LOCK ist NICHT idempotent), RFC 3253 (REPORT), RFC 5323 (SEARCH),
     * RFC 3648 (ORDERPATCH), RFC 3744 (ACL). POST und PATCH fehlen bewusst;
     * unbekannte Methoden gelten als nicht idempotent (sichere Vorgabe).
     */
    protected const IDEMPOTENT_METHODS = [
        'GET', 'HEAD', 'OPTIONS', 'TRACE',
        'PUT', 'DELETE',
        'PROPFIND', 'PROPPATCH', 'MKCOL', 'COPY', 'MOVE', 'UNLOCK',
        'REPORT', 'SEARCH', 'ORDERPATCH', 'ACL',
    ];

    /**
     * cURL-errno-Werte, die einen Fehlschlag VOR dem Senden des Requests
     * belegen: 5 COULDNT_RESOLVE_PROXY, 6 COULDNT_RESOLVE_HOST,
     * 7 COULDNT_CONNECT, 35 SSL_CONNECT_ERROR. Timeouts (28) und abgerissene
     * Verbindungen (52 GOT_NOTHING) fehlen bewusst — dort kann der Request den
     * Server bereits erreicht haben.
     */
    protected const PRE_SEND_CURL_ERRNOS = [5, 6, 7, 35];

    protected bool $sleepAfterRequest;
    protected float $lastRequestTime = 0.0;
    protected float $requestInterval = 0.25;

    protected int $maxRetries = 3;
    protected int $baseRetryDelay = 1;
    protected bool $exponentialBackoff = true;
    protected int $maxRetryDelay = 60;
    protected bool $retryNonIdempotent = false;

    protected ?AuthenticationInterface $authentication = null;

    /** @var array<string, string> */
    protected array $defaultHeaders = [];

    protected bool $verifySSL = true;

    protected bool $followRedirects = true;
    protected int $maxRedirects = 5;

    protected bool $autoIdempotencyKey = false;
    protected string $idempotencyHeader = 'Idempotency-Key';

    /** @var array<int, callable> */
    protected array $requestMiddleware = [];
    /** @var array<int, callable> */
    protected array $responseMiddleware = [];

    protected ?RateLimit $lastRateLimit = null;

    protected float $timeout = 30.0;
    protected float $connectTimeout = 10.0;

    protected ?string $proxy = null;

    protected ?string $userAgent = null;

    /** @var array<string, mixed> */
    protected array $defaultQueryParams = [];

    protected string $baseUrl;

    protected HttpClient $client;

    protected bool $httpClientInjected = false;

    protected ?\APIToolkit\API\Transport\Psr18Transport $psr18Transport = null;

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
            $this->httpClientInjected = true;
        } else {
            $this->client = new HttpClient($this->buildClientConfig());
        }
    }

    /**
     * Build the Guzzle configuration for a client the toolkit creates itself.
     *
     * Redirect following is bounded and kept strict (the request method is
     * preserved on 301/302, the Referer is not forwarded). Guzzle strips the
     * Authorization and Cookie headers on a cross-host redirect; custom auth
     * header names (e.g. an API-key header) are NOT stripped by Guzzle — for
     * APIs where that matters, disable redirect following with
     * setFollowRedirects(false).
     *
     * @return array<string, mixed>
     */
    protected function buildClientConfig(): array {
        return [
            'base_uri' => $this->baseUrl,
            'allow_redirects' => $this->followRedirects
                ? ['max' => $this->maxRedirects, 'strict' => true, 'referer' => false]
                : false,
        ];
    }

    /**
     * Get the base URL for API requests
     */
    public function getBaseUrl(): string {
        return $this->baseUrl;
    }

    /**
     * Set a new base URL.
     *
     * Recreates the internally created HTTP client with the new base URI.
     * An injected HTTP client (constructor argument) is kept as-is — its
     * base_uri cannot be changed from here; a warning is logged instead.
     *
     * @param string $baseUrl The new base URL
     */
    public function setBaseUrl(string $baseUrl): void {
        $this->baseUrl = rtrim($baseUrl, '/');

        if ($this->httpClientInjected) {
            $this->logWarning("Base URL changed to {$this->baseUrl}, but an injected HTTP client is in use — its base_uri is unaffected.");

            return;
        }

        $this->client = new HttpClient($this->buildClientConfig());
        $this->logDebug("Base URL changed to: {$this->baseUrl}");
    }

    /**
     * Enable/disable following of HTTP redirects and cap their number.
     *
     * Disabling is recommended for APIs where a server-issued redirect must
     * not carry a custom auth header (API key) to another host — Guzzle only
     * strips Authorization/Cookie automatically. Only affects a client the
     * toolkit created itself; an injected HTTP client keeps its own config.
     *
     * @param bool $follow Whether to follow redirects
     * @param int  $maxRedirects Upper bound on redirects to follow (>= 1)
     */
    public function setFollowRedirects(bool $follow, int $maxRedirects = 5): void {
        if ($maxRedirects < 1) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Max redirects must be at least 1'
            );
        }

        $this->followRedirects = $follow;
        $this->maxRedirects = $maxRedirects;

        if ($this->httpClientInjected) {
            $this->logWarning('Redirect policy changed, but an injected HTTP client is in use — its redirect config is unaffected.');

            return;
        }

        $this->client = new HttpClient($this->buildClientConfig());
    }

    public function isFollowingRedirects(): bool {
        return $this->followRedirects;
    }

    /**
     * Set the minimum interval between two requests (client-side throttling).
     *
     * Subclasses may raise the lower bound by redeclaring MIN_INTERVAL; it is
     * resolved late (static::), so an SDK-specific rate limit actually applies.
     *
     * @param float $requestInterval Interval in seconds (>= MIN_INTERVAL), or exactly 0.0 to disable throttling (e.g. in tests)
     */
    public function setRequestInterval(float $requestInterval): void {
        if ($requestInterval !== 0.0 && $requestInterval < static::MIN_INTERVAL) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Request interval must be 0 (disabled) or at least ' . static::MIN_INTERVAL . ' seconds'
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

    /**
     * Set the base delay between retry attempts.
     *
     * @param int $delay Delay in seconds; 0 disables the wait (e.g. in tests)
     */
    public function setBaseRetryDelay(int $delay): void {
        if ($delay < 0) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Base retry delay must be at least 0 seconds'
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

    /**
     * Set the upper bound (in seconds) for any retry delay, including
     * server-provided Retry-After values.
     *
     * @param int $maxRetryDelay Maximum delay in seconds; 0 caps every retry delay to no wait (e.g. in tests)
     */
    public function setMaxRetryDelay(int $maxRetryDelay): void {
        if ($maxRetryDelay < 0) {
            self::logErrorAndThrow(
                InvalidArgumentException::class,
                'Max retry delay must be at least 0 seconds'
            );
        }
        $this->maxRetryDelay = $maxRetryDelay;
    }

    public function getMaxRetryDelay(): int {
        return $this->maxRetryDelay;
    }

    /**
     * Client-weites Opt-in: Retries auch für nicht-idempotente Methoden
     * (POST, PATCH, LOCK, unbekannte Verben) nach einem gesendeten Request.
     *
     * Nur aktivieren, wenn die Ziel-API Wiederholungen serverseitig
     * dedupliziert oder Doppel-Wirkungen fachlich unkritisch sind. Alternativ
     * pro Aufruf über die Request-Option ['retry_non_idempotent' => true]
     * oder — sauberer — über einen Idempotency-Key (setAutoIdempotencyKey()/
     * Option 'idempotency_key'), der den Request wiederholbar macht.
     */
    public function setRetryNonIdempotent(bool $enabled): void {
        $this->retryNonIdempotent = $enabled;
        if ($enabled) {
            $this->logWarning('Retries für nicht-idempotente Methoden aktiviert — die Ziel-API muss Wiederholungen deduplizieren, sonst drohen Doppel-Buchungen.');
        }
    }

    public function isRetryNonIdempotentEnabled(): bool {
        return $this->retryNonIdempotent;
    }

    /**
     * Ob eine Methode laut RFC idempotent/safe ist und damit nach einem
     * gesendeten Request gefahrlos wiederholt werden darf. Unbekannte Methoden
     * gelten als nicht idempotent. Subklassen können die Einstufung über
     * IDEMPOTENT_METHODS erweitern oder diese Methode überschreiben.
     */
    protected function isIdempotentMethod(string $method): bool {
        return in_array(strtoupper($method), static::IDEMPOTENT_METHODS, true);
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
            self::logErrorAndThrow(InvalidArgumentException::class, 'Timeout must be >= 0');
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
            self::logErrorAndThrow(InvalidArgumentException::class, 'Connect timeout must be >= 0');
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
            $this->logDebug("Proxy configured: " . self::stripUrlCredentials($proxy));
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

    /**
     * Automatically attach a generated idempotency key to mutating requests
     * (POST/PUT/PATCH/DELETE) that do not already carry one. The key is
     * generated once per logical call and reused across retries, so a request
     * retried after a connection error / 5xx cannot create the resource twice.
     *
     * Ein vorhandener Idempotency-Key schaltet zugleich den Retry für
     * nicht-idempotente Methoden (POST, …) wieder frei — siehe
     * requestWithRetry().
     */
    public function setAutoIdempotencyKey(bool $enabled): void {
        $this->autoIdempotencyKey = $enabled;
    }

    public function isAutoIdempotencyKeyEnabled(): bool {
        return $this->autoIdempotencyKey;
    }

    /** Set the header name used to carry the idempotency key (default Idempotency-Key). */
    public function setIdempotencyHeader(string $header): void {
        if ($header === '') {
            self::logErrorAndThrow(InvalidArgumentException::class, 'Idempotency header name must not be empty');
        }
        $this->idempotencyHeader = $header;
    }

    public function getIdempotencyHeader(): string {
        return $this->idempotencyHeader;
    }

    /**
     * Register a request interceptor. It receives ($method, $uri, $options)
     * before the request is sent and may return a modified options array, or a
     * ResponseInterface to short-circuit the call (e.g. a cache hit). Runs
     * inside the toolkit so throttling, retry and log redaction still apply.
     */
    public function onRequest(callable $hook): void {
        $this->requestMiddleware[] = $hook;
    }

    /**
     * Register a response interceptor. It receives ($response, $method, $uri)
     * after the response is received and may return a replacement response
     * (e.g. a cache middleware turning a 304 into the cached 200).
     */
    public function onResponse(callable $hook): void {
        $this->responseMiddleware[] = $hook;
    }

    /**
     * The rate-limit budget advertised by the most recent response (parsed from
     * the X-RateLimit or IETF RateLimit headers), or null when none was seen.
     */
    public function getLastRateLimit(): ?RateLimit {
        return $this->lastRateLimit;
    }

    /**
     * Start a fluent, discoverable request builder that lowers to the same
     * options as the get()/post()/… methods.
     */
    public function pending(): \APIToolkit\API\PendingRequest {
        return new \APIToolkit\API\PendingRequest($this);
    }

    /**
     * Route requests through a PSR-18 client (with PSR-17 factories) instead of
     * the internal Guzzle client. Pass null to revert to Guzzle. Supported
     * options on this path: headers/query/json/form_params/body — per-request
     * timeout/proxy/TLS-verify/multipart are Guzzle-only and ignored.
     */
    public function setPsr18Transport(
        ?\Psr\Http\Client\ClientInterface $client,
        ?\Psr\Http\Message\RequestFactoryInterface $requestFactory = null,
        ?\Psr\Http\Message\StreamFactoryInterface $streamFactory = null
    ): void {
        if ($client === null) {
            $this->psr18Transport = null;

            return;
        }

        if ($requestFactory === null || $streamFactory === null) {
            self::logErrorAndThrow(InvalidArgumentException::class, 'A PSR-18 transport requires PSR-17 request and stream factories');
        }

        $this->psr18Transport = new \APIToolkit\API\Transport\Psr18Transport($client, $requestFactory, $streamFactory, $this->baseUrl);
    }

    /**
     * Request mit beliebiger HTTP-Methode — auch WebDAV-/CalDAV-Verben
     * (PROPFIND, MKCOL, REPORT, MOVE, COPY, LOCK, …), für die es keine
     * eigene Verb-Methode gibt.
     *
     * Läuft über exakt dieselbe Mechanik wie get()/post()/…: Throttling,
     * Default-Header/-Query, Auth, Middleware, Fehler-Mapping und der
     * methodenbewusste Retry aus requestWithRetry() (idempotente Verben
     * retryen, nicht-idempotente standardmäßig nicht). Die Methode wird
     * normalisiert (Großschreibung).
     *
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $uri, array $options = []): ResponseInterface {
        $method = strtoupper(trim($method));
        if ($method === '') {
            self::logErrorAndThrow(InvalidArgumentException::class, 'HTTP method must not be empty');
        }

        return $this->requestWithRetry($method, $uri, $options);
    }

    /** @param array<string, mixed> $options */
    public function get(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('GET', $uri, $options);
    }

    /** @param array<string, mixed> $options */
    public function post(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('POST', $uri, $options);
    }

    /** @param array<string, mixed> $options */
    public function put(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('PUT', $uri, $options);
    }

    /** @param array<string, mixed> $options */
    public function patch(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('PATCH', $uri, $options);
    }

    /** @param array<string, mixed> $options */
    public function delete(string $uri, array $options = []): ResponseInterface {
        return $this->requestWithRetry('DELETE', $uri, $options);
    }

    /**
     * Setzt genau EINEN Versuch ab (früher die private request()-Methode —
     * umbenannt, damit request() als öffentlicher Einstieg für beliebige
     * HTTP-Methoden frei wird).
     *
     * @param array<string, mixed> $options
     */
    private function send(string $method, string $uri, array $options = []): ResponseInterface {
        $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
        $sleepTime = 0;

        if ($timeSinceLastRequest < $this->requestInterval) {
            $sleepTime = (int) (($this->requestInterval - $timeSinceLastRequest) * 1e6);
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
        $authHeaderNames = [];
        if ($this->authentication !== null) {
            if ($this->authentication->isValid()) {
                $authHeaders = $this->authentication instanceof RequestAwareAuthenticationInterface
                    ? $this->authentication->getAuthHeadersFor($method, $uri, $this->extractRequestBody($options))
                    : $this->authentication->getAuthHeaders();
                $authHeaderNames = array_keys($authHeaders);
                $options['headers'] = array_merge(
                    $options['headers'] ?? [],
                    $authHeaders
                );
            } else {
                $this->logWarning("Authentication ({$this->authentication->getType()}) is set but not valid — sending {$method} request to " . self::sanitizeUriForLog($uri) . " without auth headers.");
            }
        }

        $this->logDebug("Sending {$method} request to " . self::sanitizeUriForLog($uri) . ($sleepTime > 0 ? " (waited {$sleepTime} microseconds)" : ""), $this->sanitizeOptionsForLog($options, $authHeaderNames));

        // Client-wide defaults; an explicit per-request option wins so a
        // single long-running call can raise its timeout without touching
        // the client configuration.
        $options['http_errors'] = false;
        $options['verify'] = $options['verify'] ?? $this->verifySSL;
        $options['timeout'] = $options['timeout'] ?? $this->timeout;
        $options['connect_timeout'] = $options['connect_timeout'] ?? $this->connectTimeout;

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

        // Apply default query parameters. Guzzle's "query" option replaces
        // any query string already present in the URI, so parameters from
        // the URI must be extracted and merged to survive the merge.
        // Precedence: explicit options['query'] > URI query > defaults.
        if (!empty($this->defaultQueryParams)) {
            $query = $this->defaultQueryParams;

            $uriParts = explode('?', $uri, 2);
            if (isset($uriParts[1])) {
                parse_str($uriParts[1], $uriQuery);
                $query = array_merge($query, $uriQuery);
                $uri = $uriParts[0];
            }

            $explicitQuery = $options['query'] ?? null;
            if (is_string($explicitQuery)) {
                parse_str($explicitQuery, $explicitQuery);
            }
            if (is_array($explicitQuery)) {
                $query = array_merge($query, $explicitQuery);
            }

            $options['query'] = $query;
        }

        // Request middleware may mutate the options or short-circuit the call
        // by returning a response (e.g. a cache hit).
        $shortCircuit = null;
        foreach ($this->requestMiddleware as $hook) {
            $result = $hook($method, $uri, $options);
            if ($result instanceof ResponseInterface) {
                $shortCircuit = $result;
                break;
            }
            if (is_array($result)) {
                $options = $result;
            }
        }

        $this->lastRequestTime = microtime(true);
        if ($shortCircuit !== null) {
            $response = $shortCircuit;
        } elseif ($this->psr18Transport !== null) {
            $response = $this->psr18Transport->request($method, $uri, $options);
        } else {
            $response = $this->client->request($method, $uri, $options);
        }

        $this->lastRateLimit = RateLimit::fromResponse($response) ?? $this->lastRateLimit;

        // Response middleware may replace the response (e.g. turn a conditional
        // 304 into the cached 200).
        foreach ($this->responseMiddleware as $hook) {
            $result = $hook($response, $method, $uri);
            if ($result instanceof ResponseInterface) {
                $response = $result;
            }
        }

        if ($this->sleepAfterRequest) {
            // Sleep for MIN_INTERVAL seconds after each request to ease up on
            // rate limits (independent of the pre-request requestInterval throttle).
            usleep((int) (static::MIN_INTERVAL * 1e6));
        }

        if ($response->getStatusCode() >= 400) {
            $this->handleErrorResponse($response);
        }

        return $response;
    }

    /**
     * Extract the raw request body from Guzzle options for request-aware
     * authentication (e.g. HMAC signatures over the payload).
     *
     * Mirrors Guzzle's own behavior: an explicit string "body" is used as-is,
     * a "json" option is encoded exactly like Guzzle encodes it.
     *
     * @param array<string, mixed> $options
     */
    protected function extractRequestBody(array $options): ?string {
        if (isset($options['body']) && is_string($options['body'])) {
            return $options['body'];
        }

        if (array_key_exists('json', $options)) {
            $encoded = json_encode($options['json']);

            return $encoded === false ? null : $encoded;
        }

        return null;
    }

    /**
     * Replace credentials in request options before they reach a log sink.
     *
     * Covers sensitive headers, Guzzle's "auth" option and known secret
     * parameter names in query/form_params/json; everything else is kept
     * verbatim so the debug value of the context survives.
     *
     * @param array<string, mixed> $options
     * @param array<int, string> $extraSensitiveHeaders
     * @return array<string, mixed>
     */
    protected function sanitizeOptionsForLog(array $options, array $extraSensitiveHeaders = []): array {
        // Auth implementations may use arbitrary (non-standard) header names;
        // those are passed in here so a custom key header is redacted even when
        // its name is not on the static SENSITIVE_HEADERS allowlist.
        $sensitiveHeaders = static::SENSITIVE_HEADERS;
        foreach ($extraSensitiveHeaders as $name) {
            $sensitiveHeaders[] = strtolower((string) $name);
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach (array_keys($options['headers']) as $name) {
                if (in_array(strtolower((string) $name), $sensitiveHeaders, true)) {
                    $options['headers'][$name] = self::REDACTED;
                }
            }
        }

        if (array_key_exists('auth', $options)) {
            $options['auth'] = self::REDACTED;
        }

        // A raw string body (also the payload signed by request-aware auth) may
        // carry credentials and is outside the query/form_params/json sections;
        // replace it with a length placeholder rather than logging it verbatim.
        if (isset($options['body']) && is_string($options['body'])) {
            $options['body'] = '[raw body, ' . strlen($options['body']) . ' bytes]';
        }

        // Redact secret multipart fields by their declared name.
        if (isset($options['multipart']) && is_array($options['multipart'])) {
            foreach ($options['multipart'] as $index => $part) {
                if (is_array($part) && isset($part['name']) && is_string($part['name'])
                    && in_array(strtolower($part['name']), static::SENSITIVE_PARAMS, true)) {
                    $options['multipart'][$index]['contents'] = self::REDACTED;
                }
            }
        }

        if (isset($options['query']) && is_string($options['query'])) {
            parse_str($options['query'], $parsedQuery);
            $options['query'] = $parsedQuery;
        }

        foreach (['query', 'form_params', 'json'] as $section) {
            if (isset($options[$section]) && is_array($options[$section])) {
                $options[$section] = self::redactSensitiveParams($options[$section]);
            }
        }

        return $options;
    }

    /**
     * Redact secrets embedded in a request URI before it is written to a log
     * message: strips userinfo (user:pass@) and redacts known-sensitive query
     * parameters (the same names as SENSITIVE_PARAMS).
     */
    protected static function sanitizeUriForLog(string $uri): string {
        $uri = self::stripUrlCredentials($uri);

        $parts = explode('?', $uri, 2);
        if (!isset($parts[1]) || $parts[1] === '') {
            return $uri;
        }

        parse_str($parts[1], $query);
        $query = self::redactSensitiveParams($query);

        return $parts[0] . '?' . http_build_query($query);
    }

    /**
     * @param array<array-key, mixed> $params
     * @return array<array-key, mixed>
     */
    private static function redactSensitiveParams(array $params): array {
        foreach ($params as $name => $value) {
            if (is_string($name) && in_array(strtolower($name), static::SENSITIVE_PARAMS, true)) {
                $params[$name] = self::REDACTED;
            } elseif (is_array($value)) {
                $params[$name] = self::redactSensitiveParams($value);
            }
        }

        return $params;
    }

    /** Strip the userinfo part (user:pass@) from a URL for logging. */
    private static function stripUrlCredentials(string $url): string {
        return preg_replace('~^(?:([a-z][a-z0-9+.\-]*://))?[^/@]+@~i', '$1', $url) ?? $url;
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
            // Message stays empty on purpose: the exception derives an honest
            // one itself (quota exhausted vs. rate limited vs. Retry-After).
            429 => throw new TooManyRequestsException('', 429, $response),
            500 => throw new InternalServerErrorException('Internal Server Error', 500, $response),
            502 => throw new BadGatewayException('Bad Gateway', 502, $response),
            503 => throw new ServiceUnavailableException('Service Unavailable', 503, $response),
            504 => throw new GatewayTimeoutException('Gateway Timeout', 504, $response),
            default => throw new ApiException('Unexpected response status code', $statusCode, $response, null, self::$logger),
        };
    }

    /**
     * Führt den Request mit methodenbewusstem Retry aus: Backoff bzw.
     * Retry-After bei ConnectException/429/502/503/504, dazu ein einmaliger
     * Auth-Refresh bei 401 (der 401 kam ohne Ausführung zurück und gilt für
     * alle Methoden).
     *
     * VERHALTENSÄNDERUNG gegenüber ≤ v2.9.x (Vollscan 2026-08, Befund B2):
     * Nicht-idempotente Methoden (POST, PATCH, LOCK — alles außerhalb von
     * IDEMPOTENT_METHODS) werden nach einem GESENDETEN Request nicht mehr
     * blind wiederholt: Bei 5xx/429/Transportabbruch kann der Server die
     * Wirkung bereits ausgeführt haben, ein Retry bucht sie doppelt (real
     * beobachtet: doppelte Toggl-Zeiteinträge). Auch bei POST wird weiterhin
     * wiederholt, wenn
     *  - der Fehlschlag nachweislich VOR dem Senden lag (ConnectException mit
     *    cURL-errno aus PRE_SEND_CURL_ERRNOS: DNS/Connect/TLS/Proxy),
     *  - der Request einen Idempotency-Key trägt (Option 'idempotency_key',
     *    eigener Header oder setAutoIdempotencyKey(true)) — der Server
     *    dedupliziert dann, oder
     *  - per Opt-in: Request-Option ['retry_non_idempotent' => true] bzw.
     *    client-weit setRetryNonIdempotent(true).
     * Idempotente Methoden (inkl. WebDAV: PROPFIND, MKCOL, MOVE, …) retryen
     * unverändert wie bisher.
     *
     * @param array<string, mixed> $options
     */
    protected function requestWithRetry(string $method, string $uri, array $options = []): ResponseInterface {
        // Toolkit-interne Option abräumen, bevor sie Guzzle erreicht.
        $retryNonIdempotent = (bool) ($options['retry_non_idempotent'] ?? $this->retryNonIdempotent);
        unset($options['retry_non_idempotent']);

        // Resolve the idempotency key once, before the retry loop, so every
        // attempt sends the same key.
        $options = $this->applyIdempotencyKey($method, $options);

        // Ein Idempotency-Key macht auch ein POST wiederholbar.
        $retrySafe = $retryNonIdempotent || $this->isIdempotentMethod($method) || $this->hasIdempotencyKey($options);

        $attempt = 0;
        $authRefreshTried = false;

        while ($attempt < $this->maxRetries) {
            try {
                return $this->send($method, $uri, $options);
            } catch (UnauthorizedException $e) {
                // Self-healing for server-side token invalidation: refresh
                // the credentials once and retry, then propagate the 401.
                if (!$authRefreshTried && $this->authentication instanceof RefreshableAuthenticationInterface && $this->authentication->refresh()) {
                    $authRefreshTried = true;
                    $this->logWarning("Received 401 for {$method} {$uri} — credentials refreshed, retrying once.");
                    continue;
                }

                throw $e;
            } catch (ConnectException|TooManyRequestsException|BadGatewayException|ServiceUnavailableException|GatewayTimeoutException $e) {
                // Retryable transport/5xx errors share one path. A server
                // response (for Retry-After) is only available on the toolkit's
                // typed exceptions, not on Guzzle's ConnectException.
                if (!$retrySafe && !$this->isPreSendConnectFailure($e)) {
                    // Der Request war (möglicherweise) schon beim Server — ein
                    // Retry könnte die Wirkung doppelt ausführen.
                    $this->logWarning("Kein Retry für nicht-idempotentes {$method} " . self::sanitizeUriForLog($uri) . ' nach gesendetem Request — Opt-in über retry_non_idempotent oder einen Idempotency-Key.');
                    self::logException($e);
                    throw $e;
                }

                if (!$this->shouldRetry($e)) {
                    self::logException($e);
                    throw $e;
                }

                $attempt++;
                if ($attempt >= $this->maxRetries) {
                    self::logException($e);
                    throw $e;
                }

                $response = $e instanceof ApiException ? $e->getResponse() : null;
                $delay = $this->resolveRetryDelay($attempt, $response);
                $this->logWarning("Retrying {method} {uri} after retryable error: {message} (attempt {attempt} of {maxRetries}, waiting {delay}s)", [
                    'method' => $method,
                    'uri' => self::sanitizeUriForLog($uri),
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

    /**
     * Resolve the idempotency key for a request. An explicit
     * $options['idempotency_key'] always wins; otherwise a key is generated
     * for mutating verbs when auto mode is on and none is already set.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function applyIdempotencyKey(string $method, array $options): array {
        $explicit = $options['idempotency_key'] ?? null;
        unset($options['idempotency_key']);

        if (is_string($explicit) && $explicit !== '') {
            $options['headers'][$this->idempotencyHeader] = $explicit;
        } elseif (!$this->hasIdempotencyKey($options) && $this->autoIdempotencyKey && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $options['headers'][$this->idempotencyHeader] = bin2hex(random_bytes(16));
        }

        return $options;
    }

    /**
     * Ob der Request bereits einen Idempotency-Key-Header trägt (Headername
     * case-insensitiv gegen den konfigurierten idempotencyHeader).
     *
     * @param array<string, mixed> $options
     */
    protected function hasIdempotencyKey(array $options): bool {
        foreach (array_keys($options['headers'] ?? []) as $name) {
            if (strcasecmp((string) $name, $this->idempotencyHeader) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ob der Fehlschlag nachweislich VOR dem Senden des Requests lag (DNS,
     * Connection refused, TLS-Handshake, Proxy) — dann ist auch ein Retry
     * nicht-idempotenter Methoden gefahrlos, der Server hat nie etwas gesehen.
     *
     * Guzzles ConnectException deckt AUCH Timeouts und abgerissene
     * Verbindungen ab (der Request kann dort schon unterwegs gewesen sein);
     * unterschieden wird deshalb über das cURL-errno im Handler-Kontext.
     * Fehlt der Kontext (z. B. MockHandler, Stream-Handler), gilt der
     * Fehlschlag als nicht unterscheidbar → kein Retry.
     */
    protected function isPreSendConnectFailure(\Throwable $e): bool {
        if (!$e instanceof ConnectException) {
            return false;
        }

        $errno = $e->getHandlerContext()['errno'] ?? null;

        return is_int($errno) && in_array($errno, static::PRE_SEND_CURL_ERRNOS, true);
    }

    protected function calculateRetryDelay(int $attempt): int {
        if ($this->exponentialBackoff) {
            return (int) ($this->baseRetryDelay * pow(2, $attempt - 1));
        }
        return $this->baseRetryDelay;
    }

    /**
     * Resolve the delay before the next retry attempt.
     *
     * A server-provided Retry-After header (delta-seconds or HTTP-date)
     * takes precedence over the configured backoff. Both are capped at
     * maxRetryDelay.
     */
    protected function resolveRetryDelay(int $attempt, ?ResponseInterface $response): int {
        $retryAfter = $this->retryAfterSeconds($response);

        if ($retryAfter !== null) {
            return min($retryAfter, $this->maxRetryDelay);
        }

        return min($this->calculateRetryDelay($attempt), $this->maxRetryDelay);
    }

    /**
     * Build an absolute URL from the base URL and a path — safe against
     * endpoint-style base URLs.
     *
     * Configuration fields often carry a FULL endpoint as "base URL"
     * (`https://api.openai.com/v1/responses`, Azure deployment URLs). Naive
     * concatenation with a sibling path (`/v1/models`) then yields
     * `/v1/responses/v1/models` → 404. When the first segment of the path
     * already appears in the base path, the base is capped at that segment's
     * LAST occurrence (last = cut as little as possible, so gateway prefixes
     * like `…/proxy/v1` survive). An absolute path therefore shares the base
     * host but replaces the duplicated tail; unrelated base paths are kept.
     *
     * SDKs with a clean host-only base URL are unaffected: the path is simply
     * appended, query strings pass through untouched.
     */
    public function buildUrl(string $path): string {
        $base = rtrim($this->baseUrl, '/');
        $suffix = ltrim($path, '/');
        if ($suffix === '') {
            return $base;
        }

        $basePath = trim((string) parse_url($base, PHP_URL_PATH), '/');
        if ($basePath !== '') {
            $baseSegments = explode('/', $basePath);
            $hits = array_keys($baseSegments, explode('/', explode('?', $suffix, 2)[0])[0], true);

            if ($hits !== []) {
                $drop = '/' . implode('/', array_slice($baseSegments, (int) end($hits)));
                if (str_ends_with($base, $drop)) {
                    $base = substr($base, 0, -strlen($drop));
                }
            }
        }

        return $base . '/' . $suffix;
    }

    /**
     * Whether a caught retryable-class error is actually worth retrying.
     *
     * A 429 whose body says the QUOTA is exhausted (not the rate window) is
     * not: the budget stays spent no matter how long we wait, and each retry
     * only burns time and money. Subclasses may widen or narrow this.
     */
    protected function shouldRetry(\Throwable $e): bool {
        return !($e instanceof TooManyRequestsException && $e->isQuotaExhausted());
    }

    /**
     * Seconds the server asks us to wait before the next attempt.
     *
     * Primary source is Retry-After (RFC 9110: delta-seconds and HTTP-date;
     * plus the fractional-seconds and Go-duration dialects some AI providers
     * put there). When Retry-After is missing, the rate-limit reset headers
     * (`x-ratelimit-reset-requests: 6m0s`, `anthropic-…-reset: RFC 3339`)
     * serve as the fallback hint — several AI APIs send only those.
     *
     * @return int|null Seconds to wait, or null if absent/unparseable
     */
    protected function retryAfterSeconds(?ResponseInterface $response): ?int {
        if ($response === null) {
            return null;
        }

        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value !== '') {
            return RateLimit::parseDelaySeconds($value);
        }

        return RateLimit::fromResponse($response)?->secondsUntilReset();
    }
}
