# PHP API Toolkit - Copilot Instructions

## Project Overview
This is a reusable PHP library for building API client SDKs, targeting PHP 8.2+ with modern patterns and PSR compliance. The codebase provides foundational abstractions for HTTP clients, entities, and error handling.

## Architecture & Core Patterns

### Client Architecture
- **Base Client**: All API clients extend [`ClientAbstract`](src/Contracts/Abstracts/API/ClientAbstract.php) which provides:
  - Rate limiting with configurable intervals (min 0.2s, default 0.25s)
  - HTTP error mapping to specific exceptions (400→BadRequestException, 401→UnauthorizedException, etc.)
  - Built-in retry logic with exponential backoff
  - Comprehensive logging using PSR-3 logger interface

### Entity System
- **Named Entities**: Core data objects extend [`NamedEntity`](src/Contracts/Abstracts/NamedEntity.php) with:
  - Automatic data hydration from arrays/objects
  - Built-in validation via `isValid()` method
  - Property reflection and type-safe assignment
  - Entity name tracking for debugging

- **Value Objects**: Simple values extend [`NamedValue`](src/Contracts/Abstracts/NamedValue.php)
- **ID Types**: [`ID`](src/Entities/ID.php), [`UUID`](src/Entities/UUID.php), [`GUID`](src/Entities/GUID.php) with validation
- **Versioning**: [`Version`](src/Entities/Version.php), [`ProgramVersion`](src/Entities/ProgramVersion.php) entities

### Exception Hierarchy
All API exceptions extend [`ApiException`](src/Exceptions/ApiException.php) with:
- Automatic response body logging
- HTTP status code mapping
- PSR-3 logger integration
- Specific exceptions per HTTP status (BadRequestException, UnauthorizedException, etc.)

## Development Workflows

### Testing
```bash
# Run all tests
composer test
# or
vendor/bin/phpunit

# Test configuration in phpunit.xml excludes ./tests/Contracts
```

### Dependencies
- **HTTP Client**: Uses GuzzleHttp/Guzzle for all HTTP operations
- **Logging**: PSR-3 logger interface with ErrorToolkit integration
- **UUIDs**: Ramsey/UUID for UUID generation and validation
- **Common Utilities**: Custom dschuppelius/php-common-toolkit

## Key Conventions

### Rate Limiting
Always configure appropriate request intervals on client instantiation:
```php
$client = new MyApiClient($httpClient, $logger);
$client->setRequestInterval(0.5); // 500ms between requests
```

### Error Handling
HTTP errors are automatically converted to typed exceptions. Catch specific types:
```php
try {
    $response = $client->get('/endpoint');
} catch (TooManyRequestsException $e) {
    // Increase request interval
} catch (UnauthorizedException $e) {
    // Handle auth failure
}
```

### Entity Validation
All entities must implement `isValid()`. Use this pattern:
```php
$entity = new MyEntity($data);
if (!$entity->isValid()) {
    throw new InvalidArgumentException('Invalid entity data');
}
```

### Logging Integration
All classes use ErrorLog trait. Initialize logger in constructor:
```php
public function __construct(?LoggerInterface $logger = null) {
    $this->initializeLogger($logger);
}
```

## Project Structure
- `src/Contracts/` - Interfaces and abstract base classes
- `src/Entities/` - Data model classes organized by domain (Bank/, Contact/, etc.)
- `src/Enums/` - Enumeration types
- `src/Exceptions/` - HTTP and validation exception hierarchy
- `tests/` - PHPUnit tests with mocking patterns

## Testing Patterns
- Extend [`Test`](tests/Contracts/Test.php) base class
- Mock HttpClient for client tests
- Use reflection testing for entities
- Validate both success and error scenarios