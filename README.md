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

## Rate Limiting

```php
// Set minimum interval between requests (default: 0.25s, minimum: 0.2s)
$client->setRequestInterval(0.5); // 500ms between requests
```

## Retry Logic

```php
// Configure retry behavior for 429, 503, 504 errors
$client->setMaxRetries(5);
$client->setBaseRetryDelay(2); // seconds
$client->setExponentialBackoff(true); // delays: 2s, 4s, 8s, 16s...
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
    // 429 - Rate limited
}
```

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

```bash
composer test
# or
vendor/bin/phpunit
```

## License

MIT License - see [LICENSE](LICENSE) for details.
