# PHP API Toolkit

A reusable PHP library for building API client SDKs, targeting PHP 8.2+ with modern patterns and PSR compliance.

## Features

- **HTTP Client Abstraction** - Built on GuzzleHttp with automatic rate limiting and retry logic
- **Authentication Strategies** - Built-in support for Bearer, Basic, API Key and OAuth2 (Authorization Code incl. PKCE/revocation, Client Credentials incl. private_key_jwt)
- **Exception Mapping** - HTTP status codes automatically mapped to specific exceptions
- **Entity System** - Type-safe data objects with automatic hydration and validation
- **PSR-3 Logging** - Comprehensive logging integration
- **Timeouts** - Configurable request and connection timeouts
- **Proxy Support** - Enterprise-ready proxy configuration
- **Default Headers & Query Params** - Global request configuration
- **SSL Control** - Optional SSL verification bypass for development

## Building blocks

The toolkit carries more than the client base class. Which of these an SDK
needs depends on the API it wraps — the ones an SDK does not use are meant for
the *application* embedding it, not dead weight:

| Building block | Use it for | Used by the SDKs |
| --- | --- | --- |
| `Contracts\Abstracts\API\ClientAbstract` | client base: throttling, retry, log redaction | all |
| `Contracts\Abstracts\API\EndpointAbstract` | endpoint base: request helpers, response hydration | all |
| `Contracts\Abstracts\API\PagedEndpointAbstract` | `searchAll()` over page/size or limit/offset APIs | lexoffice, orgamax |
| `API\Pagination\OffsetPaginator` | page-number / offset pagination | via PagedEndpointAbstract |
| `API\Pagination\LinkHeaderPaginator`, `LinkHeader` | RFC 8288 `Link` header pagination | datev |
| `API\Pagination\CursorPaginator` | cursor/continuation-token pagination | — (no API needs it yet) |
| `API\Authentication\*` | Bearer, Basic, API key, HMAC, OAuth2 grants | Bearer/Basic (all), OAuth2 (datev) |
| `Contracts\Interfaces\API\RefreshableAuthenticationInterface` | self-healing credentials after a 401 | orgamax |
| `API\Webhook\WebhookVerifier` | verifying inbound webhook signatures | — application-side, see below |
| `API\Cache\Psr16ResponseCache` | conditional requests / response caching | — application-side |
| `API\PendingRequest` | fluent one-off requests outside an endpoint | — application-side |
| `API\Transport\Psr18Transport` | routing through a PSR-18 client instead of Guzzle | — application-side |
| `Testing\MockApiClient` | endpoint tests without HTTP | lexoffice, orgamax |

**Application-side** means: an SDK exposes the API surface, but signature
verification, caching and transport choice belong to the application that
receives the webhooks, owns the cache and picks the HTTP stack. Wiring them
into an SDK would decide those questions for every consumer.

## Installation

```bash
composer require dschuppelius/php-api-toolkit
```

## Quick Start

```php
use APIToolkit\Contracts\Abstracts\API\Authentication\BearerAuthentication;

// Create your API client extending ClientAbstract
// Simply pass the base URL - HttpClient is created internally
$apiClient = new MyApiClient('https://api.example.com', $logger);

// Set authentication
$auth = new BearerAuthentication('your-token');
$apiClient->setAuthentication($auth);

// Make requests - auth headers are added automatically
$response = $apiClient->get('/endpoint');
```

### Advanced: Custom HttpClient

For advanced use cases (custom middleware, handler stacks, etc.), you can still provide your own HttpClient:

```php
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
// Add custom middleware...

$httpClient = new HttpClient([
    'base_uri' => 'https://api.example.com',
    'handler' => $stack,
]);

$apiClient = new MyApiClient('https://api.example.com', $logger, false, $httpClient);
```

## Authentication

The toolkit provides three authentication strategies out of the box:

### Bearer Token (OAuth2, JWT)

```php
use APIToolkit\Contracts\Abstracts\API\Authentication\BearerAuthentication;

$auth = new BearerAuthentication('your-jwt-token');
$client->setAuthentication($auth);

// Update token later (e.g., after refresh)
$auth->setToken('new-refreshed-token');
```

### Basic Authentication

```php
use APIToolkit\Contracts\Abstracts\API\Authentication\BasicAuthentication;

$auth = new BasicAuthentication('username', 'password');
$client->setAuthentication($auth);
```

### API Key

```php
use APIToolkit\Contracts\Abstracts\API\Authentication\ApiKeyAuthentication;

// Default header: X-API-Key
$auth = new ApiKeyAuthentication('your-api-key');

// Custom header name
$auth = new ApiKeyAuthentication('your-api-key', 'X-Custom-Auth');
$client->setAuthentication($auth);
```

### OAuth2 Client Credentials (Machine-to-Machine)

For APIs without user interaction (e.g. UPS, FedEx, Microsoft Graph app-only).
Tokens are fetched, cached and re-fetched on expiry automatically; after a 401
the token is discarded and the request retried exactly once.

```php
use APIToolkit\API\Authentication\OAuth2\{OAuth2ClientCredentialsAuthentication, OAuth2ClientCredentialsGrant};

$grant = new OAuth2ClientCredentialsGrant(
    'client-id',
    'client-secret',
    'https://provider.example.com/oauth/token'
);

// Client authentication at the token endpoint:
// default: credentials in the form body (e.g. FedEx)
$grant->setTokenAuthMethod(OAuth2ClientCredentialsGrant::AUTH_METHOD_BASIC); // HTTP Basic header (e.g. UPS)

// Token persistence is pluggable via OAuth2TokenStoreInterface
// (default: in-memory). Inject e.g. an encrypted per-tenant store:
$auth = new OAuth2ClientCredentialsAuthentication($grant, $myTokenStore, ['read', 'write']);
$client->setAuthentication($auth);
```

Certificate-based clients (private_key_jwt, RFC 7523 — e.g. Microsoft Entra ID
certificate credentials) sign a JWT client assertion instead of sending a
secret (requires the openssl extension):

```php
$grant = new OAuth2ClientCredentialsGrant('client-id', '', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token');
$grant->setPrivateKeyJwt($privateKeyPem, $certificatePem); // certificate adds the x5t/x5t#S256 header

$auth = new OAuth2ClientCredentialsAuthentication($grant, null, ['https://graph.microsoft.com/.default']);
$client->setAuthentication($auth);
```

### Custom Authentication

Implement `AuthenticationInterface` for custom auth strategies:

```php
use APIToolkit\Contracts\Interfaces\API\AuthenticationInterface;

class OAuth1Authentication implements AuthenticationInterface {
    public function getAuthHeaders(): array {
        return ['Authorization' => 'OAuth ' . $this->buildOAuthHeader()];
    }
    
    public function getType(): string {
        return 'OAuth1';
    }
    
    public function isValid(): bool {
        return !empty($this->consumerKey);
    }
}
```

## Endpoint-style Base URLs

Configuration fields often carry a full endpoint as "base URL". `buildUrl()`
merges such a base with a sibling path without duplicating segments:

```php
$client = new MyClient('https://api.openai.com/v1/responses');
$client->buildUrl('/v1/models'); // https://api.openai.com/v1/models
// Gateway prefixes survive: base https://gw.example.com/proxy/v1
// + /v1/models => https://gw.example.com/proxy/v1/models
```

## Rate Limiting

```php
// Set minimum interval between requests (default: 0.25s, minimum: 0.2s)
$client->setRequestInterval(0.5); // 500ms between requests
```

## Retry Logic

```php
// Configure retry behavior for 429, 502, 503, 504 and connect errors
$client->setMaxRetries(5);
$client->setBaseRetryDelay(2); // seconds
$client->setExponentialBackoff(true); // delays: 2s, 4s, 8s, 16s...
```

The server's wait hint wins over the backoff: `Retry-After` is honored in all
its spellings — delta-seconds, HTTP-date, fractional seconds and Go durations
(`6m0s`, as sent by OpenAI). When `Retry-After` is missing, the rate-limit
reset headers (`x-ratelimit-reset-requests`, `anthropic-ratelimit-…-reset`)
serve as the fallback hint.

**Quota errors are not retried.** A 429 whose body carries a quota code
(`insufficient_quota`, `billing_hard_limit_reached`, …) means the budget is
spent, not the rate window — retrying is futile, so the exception is thrown
immediately. Override `shouldRetry()` to change the policy.

**Retries are method-aware (BEHAVIOR CHANGE in > v2.9.1).** Only idempotent
methods (RFC 7231/4918: GET, HEAD, OPTIONS, TRACE, PUT, DELETE, plus the
WebDAV verbs PROPFIND, PROPPATCH, MKCOL, COPY, MOVE, UNLOCK, REPORT, SEARCH,
ORDERPATCH, ACL) are retried after a request went out. Non-idempotent methods
(POST, PATCH, LOCK, unknown verbs) are **no longer retried blindly** on
429/5xx/transport errors — the server may already have executed the action,
and a retry would perform it twice (observed in the wild as duplicated Toggl
time entries). A POST is still retried when

- the failure provably happened **before** the request was sent
  (`ConnectException` with a DNS/connect/TLS/proxy cURL errno),
- the request carries an idempotency key (`idempotency_key` option, own
  header, or `setAutoIdempotencyKey(true)`) — the server deduplicates, or
- you opt in: per request via `['retry_non_idempotent' => true]` or
  client-wide via `setRetryNonIdempotent(true)`.

## Arbitrary HTTP methods (WebDAV/CalDAV)

`request()` sends any HTTP method through the full client pipeline
(throttling, auth, middleware, method-aware retry, error mapping) — no need
to fall back to raw Guzzle for WebDAV/CalDAV verbs:

```php
$response = $client->request('PROPFIND', '/calendars/user/', [
    'headers' => ['Depth' => '1'],
    'body'    => $propfindXml,
]);
$client->request('MKCOL', '/dav/new-folder/');
$client->request('REPORT', '/calendars/user/', ['body' => $calendarQuery]);
```

```php
try {
    $client->post('/v1/responses', ['json' => $payload]);
} catch (TooManyRequestsException $e) {
    if ($e->isQuotaExhausted()) {
        // top up the plan — waiting will not help
    }
}
```

## Timeouts

```php
// Request timeout (default: 30s)
$client->setTimeout(60.0);

// Connection timeout (default: 10s)
$client->setConnectTimeout(5.0);
```

## Default Headers

```php
// Set headers included in every request
$client->setDefaultHeaders([
    'Content-Type' => 'application/json;charset=utf-8',
    'Accept' => 'application/json;charset=utf-8',
]);

// Add/remove individual headers
$client->addDefaultHeader('X-Custom-Header', 'value');
$client->removeDefaultHeader('X-Custom-Header');
```

## Default Query Parameters

```php
// Set query parameters included in every request
$client->setDefaultQueryParams([
    'api_version' => '2.0',
    'format' => 'json',
]);

// Add/remove individual parameters
$client->addDefaultQueryParam('locale', 'de_DE');
$client->removeDefaultQueryParam('format');
```

## User-Agent

```php
$client->setUserAgent('MyApp/1.0.0 (PHP 8.2)');
```

## Proxy Support

```php
// Set proxy for enterprise environments
$client->setProxy('http://proxy.company.com:8080');

// With authentication
$client->setProxy('http://user:pass@proxy.company.com:8080');

// Disable proxy
$client->setProxy(null);
```

## SSL Verification

```php
// Disable SSL verification (development only!)
$client->setVerifySSL(false);

// Check status
$client->isSSLVerificationEnabled();
```

> ⚠️ **Warning:** Disabling SSL verification is insecure. Only use for development or self-signed certificates.

## Complete Configuration Example

```php
use APIToolkit\Contracts\Abstracts\API\Authentication\BearerAuthentication;

// Simple constructor - just base URL
$client = new MyApiClient('https://api.example.com', $logger);

// Timeouts
$client->setTimeout(30.0);
$client->setConnectTimeout(10.0);

// Default headers
$client->setDefaultHeaders([
    'Content-Type' => 'application/json;charset=utf-8',
    'Accept' => 'application/json;charset=utf-8',
]);

// User-Agent
$client->setUserAgent('MyApp/1.0.0');

// Default query parameters
$client->setDefaultQueryParams(['api_version' => '2']);

// Authentication with additional headers
$auth = new BearerAuthentication($token, [
    'X-Client-ID' => $clientId,
]);
$client->setAuthentication($auth);

// Rate limiting
$client->setRequestInterval(0.5);
$client->setMaxRetries(3);

// Enterprise environment (optional)
$client->setProxy('http://proxy:8080');
$client->setVerifySSL(false); // Development only!
```

## Exception Handling

HTTP errors are automatically converted to typed exceptions:

```php
use APIToolkit\Exceptions\TooManyRequestsException;
use APIToolkit\Exceptions\UnauthorizedException;
use APIToolkit\Exceptions\NotFoundException;

try {
    $response = $client->get('/resource');
} catch (UnauthorizedException $e) {
    // 401 - Invalid or expired token
} catch (NotFoundException $e) {
    // 404 - Resource not found
} catch (TooManyRequestsException $e) {
    // 429 - Rate limited or quota exhausted ($e->isQuotaExhausted())
}
```

Every `ApiException` exposes the parsed error envelope: `getErrorCode()` /
`getErrorCodes()` (flat and nested `{"error": {"code", "type"}}` forms),
`getErrorMessage()` and `getProblemDetails()` (RFC 7807). The static
`ApiException::errorCodesOf($response)` reads the codes from a PSR-7 response
without consuming its body.

| Status Code | Exception |
|-------------|-----------|
| 400 | `BadRequestException` |
| 401 | `UnauthorizedException` |
| 402 | `PaymentRequiredException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 405 | `NotAllowedException` |
| 406 | `NotAcceptableException` |
| 408 | `RequestTimeoutException` |
| 409 | `ConflictException` |
| 415 | `UnsupportedMediaTypeException` |
| 422 | `UnprocessableEntityException` |
| 429 | `TooManyRequestsException` |
| 500 | `InternalServerErrorException` |
| 502 | `BadGatewayException` |
| 503 | `ServiceUnavailableException` |
| 504 | `GatewayTimeoutException` |

## Testing

`Testing\MockApiClient` implements `ApiClientInterface` and answers registered
method/URI patterns, so endpoint logic can be tested without HTTP:

```php
$client = (new APIToolkit\Testing\MockApiClient())
    ->addResponse('GET', 'article/90', 200, '{"id":90}')
    ->addResponse('*', 'invoice/*', 204, '');

$article = (new ArticlesEndpoint($client))->get(new ID(90));
$client->getLastRequest(); // ['method' => 'GET', 'uri' => 'article/90', 'options' => []]
```

It covers endpoint behaviour, **not** the transport. URL building, headers,
auth and retry only get exercised by a test against the real client with an
injected Guzzle `MockHandler` — that is the layer where a swallowed base path
or a wrong `Content-Type` hides.

```bash
composer test
# or
vendor/bin/phpunit
```

## License

MIT License - see [LICENSE](LICENSE) for details.
