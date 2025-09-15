<?php

use LucaLongo\LaravelLicensingClient\Http\Middleware\CheckLicense;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

beforeEach(function () {
    $this->client = Mockery::mock(LaravelLicensingClient::class);
    $this->middleware = new CheckLicense($this->client);
    $this->request = Request::create('/test', 'GET');
    $this->next = fn($request) => response('OK');
    config(['licensing-client.license_key' => 'TEST-KEY']);
});

it('allows request when license is valid', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(true);

    $this->client->shouldReceive('heartbeat')
        ->with('TEST-KEY')
        ->once();

    $this->client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-KEY')
        ->once()
        ->andReturn(false);

    $response = $this->middleware->handle($this->request, $this->next);

    expect($response->getContent())->toBe('OK');
});

it('allows excluded routes without checking license', function () {
    config(['licensing-client.excluded_routes' => ['test']]);

    $this->client->shouldNotReceive('isValid');

    $response = $this->middleware->handle($this->request, $this->next);

    expect($response->getContent())->toBe('OK');
});

it('tries to refresh when license is invalid', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('refresh')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(true);

    $this->client->shouldReceive('heartbeat')
        ->with('TEST-KEY')
        ->once();

    $this->client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-KEY')
        ->once()
        ->andReturn(false);

    $response = $this->middleware->handle($this->request, $this->next);

    expect($response->getContent())->toBe('OK');
});

it('starts grace period when server is unreachable', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('refresh')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isInGracePeriod')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isServerHealthy')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('startGracePeriod')
        ->once();

    $response = $this->middleware->handle($this->request, $this->next);

    expect($response->getContent())->toBe('OK');
});

it('allows request during grace period', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('refresh')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isInGracePeriod')
        ->once()
        ->andReturn(true);

    $this->client->shouldReceive('heartbeat')
        ->with('TEST-KEY')
        ->once();

    $this->client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-KEY')
        ->once()
        ->andReturn(false);

    $response = $this->middleware->handle($this->request, $this->next);

    expect($response->getContent())->toBe('OK');
});

it('blocks request when license is invalid and not in grace period', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('refresh')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isInGracePeriod')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isServerHealthy')
        ->once()
        ->andReturn(true);

    $this->middleware->handle($this->request, $this->next);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('returns JSON error for API requests', function () {
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('Accept', 'application/json');

    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('refresh')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isInGracePeriod')
        ->once()
        ->andReturn(false);

    $this->client->shouldReceive('isServerHealthy')
        ->once()
        ->andReturn(true);

    $response = $this->middleware->handle($request, $this->next);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKeys(['error', 'code']);
    expect($data['code'])->toBe('LICENSE_INVALID');
});

it('adds expiration warning to request attributes', function () {
    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(true);

    $this->client->shouldReceive('heartbeat')
        ->with('TEST-KEY')
        ->once();

    $this->client->shouldReceive('isExpiringSoon')
        ->with(7, 'TEST-KEY')
        ->once()
        ->andReturn(true);

    $this->client->shouldReceive('getLicenseInfo')
        ->with('TEST-KEY')
        ->once()
        ->andReturn([
            'expires_at' => now()->addDays(5)->toIso8601String(),
        ]);

    $response = $this->middleware->handle($this->request, $this->next);

    expect($this->request->attributes->get('license_expiring_soon'))->toBeTrue();
    expect($this->request->attributes->get('license_expires_at'))->not->toBeNull();
});

it('handles license exceptions properly', function () {
    $exception = LicensingException::licenseExpired();

    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andThrow($exception);

    $this->middleware->handle($this->request, $this->next);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'The license has expired.');

it('returns JSON error for license exceptions in API requests', function () {
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('Accept', 'application/json');

    $exception = LicensingException::licenseExpired();

    $this->client->shouldReceive('isValid')
        ->with('TEST-KEY')
        ->once()
        ->andThrow($exception);

    $response = $this->middleware->handle($request, $this->next);

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(403);

    $data = json_decode($response->getContent(), true);
    expect($data['error'])->toBe('The license has expired.');
    expect($data['code'])->toBe('LICENSE_ERROR');
});