# Laravel Licensing Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-licensing-client.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-licensing-client)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-licensing-client/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/masterix21/laravel-licensing-client/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/masterix21/laravel-licensing-client/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/masterix21/laravel-licensing-client/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-licensing-client.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-licensing-client)

A Laravel package for integrating license validation in your applications. Works with the [Laravel Licensing](https://github.com/masterix21/laravel-licensing) server to provide secure, offline-capable license management using PASETO v4 tokens with Ed25519 signatures.

## Related Packages

- **[Laravel Licensing Server](https://github.com/masterix21/laravel-licensing)** - Server-side license management
- **[Laravel Licensing Filament Manager](https://github.com/masterix21/laravel-licensing-filament-manager)** - Admin panel built with Filament

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require masterix21/laravel-licensing-client
```

Publish the configuration:

```bash
php artisan vendor:publish --tag="licensing-client-config"
```

Run the migrations:

```bash
php artisan migrate
```

## Configuration

Add these variables to your `.env`:

```env
LICENSING_SERVER_URL=https://your-licensing-server.com
LICENSING_PUBLIC_KEY=your-base64-encoded-ed25519-public-key
LICENSING_KEY=LIC-XXXX-XXXX-XXXX-XXXX
```

The full configuration is in `config/licensing-client.php`:

```php
return [
    'server_url' => env('LICENSING_SERVER_URL', 'https://licensing.example.com'),
    'api_version' => env('LICENSING_API_VERSION', 'v1'),
    'license_key' => env('LICENSING_KEY'),
    'public_key' => env('LICENSING_PUBLIC_KEY'),
    'issuer' => env('LICENSING_ISSUER', 'laravel-licensing'),

    'cache' => [
        'enabled' => env('LICENSING_CACHE_ENABLED', true),
        'store' => env('LICENSING_CACHE_STORE', 'file'),
        'ttl' => env('LICENSING_CACHE_TTL', 3600),
    ],

    'heartbeat' => [
        'enabled' => env('LICENSING_HEARTBEAT_ENABLED', true),
        'interval' => env('LICENSING_HEARTBEAT_INTERVAL', 3600),
    ],

    'grace_period_days' => env('LICENSING_GRACE_PERIOD_DAYS', 7),
    'timeout' => env('LICENSING_TIMEOUT', 30),
    'debug' => env('LICENSING_DEBUG', false),
    'storage_path' => storage_path('app/licensing'),

    'excluded_routes' => [
        'login',
        'register',
        'password/*',
        'licensing/*',
    ],
];
```

## Usage

### Facade

```php
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;

// Activate
LaravelLicensingClient::activate('LIC-XXXX-XXXX-XXXX-XXXX');

// Check validity (offline, from stored PASETO token)
LaravelLicensingClient::isValid();

// Validate with exception on failure
$claims = LaravelLicensingClient::validate();

// Get license info from token claims
$info = LaravelLicensingClient::getLicenseInfo();
// Returns: [
//     'license_id' => 1,
//     'license_key_hash' => 'sha256...',
//     'status' => 'active',
//     'max_usages' => 5,
//     'expires_at' => '2027-01-07T00:00:00+00:00',
//     'issued_at' => '2027-01-01T00:00:00+00:00',
//     'license_expires_at' => '2027-12-31T23:59:59+00:00',
//     'force_online_after' => '2027-01-14T00:00:00+00:00',
//     'grace_until' => null,
//     'usage_fingerprint' => 'sha256...',
// ]

// Check expiration warnings
LaravelLicensingClient::isExpiringSoon(7);

// Check if online refresh is required (force_online_after exceeded)
LaravelLicensingClient::requiresOnlineRefresh();

// Proactive refresh check (based on refresh_after from server)
LaravelLicensingClient::shouldRefreshProactively();

// Refresh the token
LaravelLicensingClient::refresh();

// Deactivate (with optional reason)
LaravelLicensingClient::deactivate('LIC-XXXX-XXXX-XXXX-XXXX', 'switching device');

// Server health
LaravelLicensingClient::isServerHealthy();
```

### Dependency Injection

```php
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class LicenseController extends Controller
{
    public function status(LaravelLicensingClient $licensing)
    {
        return response()->json([
            'valid' => $licensing->isValid(),
            'info' => $licensing->getLicenseInfo(),
            'requires_refresh' => $licensing->requiresOnlineRefresh(),
        ]);
    }
}
```

## Middleware

Protect routes with the `license` middleware:

```php
// Single route
Route::get('/dashboard', DashboardController::class)->middleware('license');

// Route group
Route::middleware('license')->group(function () {
    Route::get('/reports', ReportsController::class);
    Route::get('/analytics', AnalyticsController::class);
});
```

### Middleware Behavior

The middleware follows this flow:

1. Check if the route is excluded
2. Validate the stored token offline
3. If valid, check `force_online_after` — refresh if past date
4. If token invalid, attempt refresh from server
5. If refresh fails, check client-side grace period
6. If not in grace period, check server health
7. If server unreachable, start grace period and allow access
8. If server healthy and no valid license, block with 403

On valid requests, the middleware also:
- Sends heartbeat if interval has elapsed
- Sets `license_expiring_soon` and `license_expires_at` as request attributes if expiration is near

### Accessing Expiration Warnings

```php
public function dashboard(Request $request)
{
    if ($request->attributes->get('license_expiring_soon')) {
        $expiresAt = $request->attributes->get('license_expires_at');
        // Show renewal warning
    }
}
```

### Excluding Routes

Configure in `config/licensing-client.php`:

```php
'excluded_routes' => [
    'login',
    'register',
    'password/*',
    'api/health',
],
```

## Grace Period

The client manages a local grace period when the licensing server is unreachable:

```php
// Check if in grace period
LaravelLicensingClient::isInGracePeriod();

// Manually start (useful for testing)
LaravelLicensingClient::startGracePeriod();
```

The default grace period is 7 days, configurable via `LICENSING_GRACE_PERIOD_DAYS`.

The middleware automatically enters grace period when the server is unreachable, allowing the application to continue working.

## Artisan Commands

```bash
# Activate a license (interactive if no key provided)
php artisan license:activate LIC-XXXX-XXXX-XXXX-XXXX

# Validate current license
php artisan license:validate

# Display license details
php artisan license:info

# Refresh token from server
php artisan license:refresh

# Deactivate license (with confirmation prompt)
php artisan license:deactivate
```

## Heartbeat

When enabled, the package automatically sends heartbeats to the licensing server at the configured interval. The heartbeat reports:

- Laravel version
- Application environment

Configure in `.env`:

```env
LICENSING_HEARTBEAT_ENABLED=true
LICENSING_HEARTBEAT_INTERVAL=3600  # seconds
```

The heartbeat is registered as a scheduled task in the service provider and runs via Laravel's scheduler.

## Token Validation

The client validates PASETO v4 tokens offline using the Ed25519 public key. The following claims are validated:

| Claim | Validation |
|---|---|
| `usage_fingerprint` | Must match the current device fingerprint |
| `exp` | Token must not be expired |
| `status` | Must be `active` or `grace` |
| `force_online_after` | If past, an online refresh is required |

The client also stores the `public_key_bundle` received from the server during activation and refresh, enabling future key rotation support.

## Device Fingerprinting

The client generates a stable SHA-256 fingerprint from:

- Hostname
- Machine ID (platform-specific: `/etc/machine-id`, `IOPlatformUUID`, WMI UUID)
- PHP version
- Laravel version
- Application key

This fingerprint is sent to the server during activation to bind the license to the device.

### Custom Fingerprint Generator

```php
use LucaLongo\LaravelLicensingClient\Services\FingerprintGenerator;

class CustomFingerprintGenerator extends FingerprintGenerator
{
    public function generate(): string
    {
        $components = [
            $this->getHostname(),
            $this->getMachineId(),
            config('app.deployment_id'),
        ];

        return hash('sha256', implode('|', array_filter($components)));
    }
}

// Register in a service provider
$this->app->bind(FingerprintGenerator::class, CustomFingerprintGenerator::class);
```

## Error Handling

The package throws `LicensingException` with specific factory methods:

```php
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;

try {
    LaravelLicensingClient::validate();
} catch (LicensingException $e) {
    // Possible messages:
    // - "The provided license key is invalid."
    // - "The license has expired."
    // - "The license has not been activated."
    // - "The license has been suspended."
    // - "The license has been cancelled."
    // - "Device fingerprint does not match the licensed device."
    // - "The fingerprint is already in use by another device."
    // - "License usage limit has been exceeded."
    // - "Offline tokens are not enabled for this license."
    // - "Too many requests to the licensing server. Please try again later."
    // - "Online verification is required. Please connect to the internet."
    // - "Unable to reach the licensing server."
    // - "The license token is invalid or corrupted."
    // - "Public key for token verification is not configured."
}
```

## API Communication

The client communicates with the server at `/api/licensing/v1/` using these endpoints:

| Method | Endpoint | Description |
|---|---|---|
| POST | `/activate` | Activate a license with fingerprint |
| POST | `/deactivate` | Deactivate a license |
| POST | `/refresh` | Refresh the PASETO token |
| POST | `/heartbeat` | Send heartbeat with usage data |
| POST | `/validate` | Validate license server-side |
| POST | `/licenses/show` | Get license information |
| GET | `/health` | Check server health |

All responses follow the format:

```json
{
    "success": true,
    "data": { ... }
}
```

Error responses:

```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Human-readable message"
    }
}
```

The client handles these HTTP error codes: 404 (invalid key), 403 (fingerprint mismatch / not active), 409 (usage limit / fingerprint conflict / offline disabled), 410 (expired), 422 (validation failed), 423 (suspended / cancelled), 429 (rate limited).

## Testing

### In Your Application Tests

```php
use Illuminate\Support\Facades\Http;
use LucaLongo\LaravelLicensingClient\Facades\LaravelLicensingClient;

public function test_protected_route(): void
{
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => 'v4.public.test-token...',
                'license' => ['id' => 'ulid', 'status' => 'active'],
            ],
        ]),
    ]);

    LaravelLicensingClient::activate('TEST-KEY');

    $this->get('/protected-route')->assertStatus(200);
}
```

### Mocking the Client

```php
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

$mock = Mockery::mock(LaravelLicensingClient::class);
$mock->shouldReceive('isValid')->andReturn(true);
$mock->shouldReceive('getLicenseInfo')->andReturn([
    'status' => 'active',
    'max_usages' => 5,
]);

$this->app->instance(LaravelLicensingClient::class, $mock);
```

### Running Package Tests

```bash
composer test              # Run all tests
composer test-coverage     # Run with coverage
composer analyse           # PHPStan static analysis
composer format            # Laravel Pint formatting
```

## Development Roadmap

The following features are planned for future releases:

### Phase 1 — Token Security
- `iss` (issuer) claim validation
- `nbf` (not before) claim validation
- Clock skew tolerance (configurable, default ±60s)

### Phase 2 — Features & Entitlements
- `hasFeature(string $feature): bool` and `getFeatures(): array`
- `getEntitlement(string $key, mixed $default = null): mixed`
- Feature-gating middleware: `Route::middleware('license:premium_export')`
- Features and entitlements are stored from the API response (not in the token)

### Phase 3 — Network Resilience
- Automatic retry with exponential backoff via `Http::retry()`

### Phase 4 — License Information
- Seat info: `getSeatsInfo()` (active/available/max usages)
- License vs token expiry distinction: `isLicenseExpiringSoon()`
- Server-side grace period awareness from `grace_until` token claim

### Phase 5 — Key Rotation
- Public key selection via `kid` from token footer
- Proactive scheduled token refresh based on `refresh_after`

## Contributing

Contributions are welcome! Please submit a Pull Request.

## License

MIT License. See [LICENSE.md](LICENSE.md).

## Credits

- [Luca Longo](https://github.com/masterix21)
