<?php

use LucaLongo\LaravelLicensingClient\Services\TokenStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->storage = new TokenStorage();
});

it('stores and retrieves a token', function () {
    $token = 'test-token-123';
    $key = 'test-key';

    $this->storage->store($token, $key);
    $retrieved = $this->storage->retrieve($key);

    expect($retrieved)->toBe($token);
});

it('encrypts token when storing', function () {
    $token = 'sensitive-token';
    $key = 'encryption-test';

    $this->storage->store($token, $key);

    $storagePath = config('licensing-client.storage_path');
    $filename = $storagePath . '/' . hash('sha256', $key) . '.token';

    $storedContent = File::get($filename);

    expect($storedContent)->not->toContain($token);
});

it('returns null when token does not exist', function () {
    $retrieved = $this->storage->retrieve('non-existent');

    expect($retrieved)->toBeNull();
});

it('deletes a stored token', function () {
    $token = 'token-to-delete';
    $key = 'delete-test';

    $this->storage->store($token, $key);
    expect($this->storage->exists($key))->toBeTrue();

    $this->storage->delete($key);
    expect($this->storage->exists($key))->toBeFalse();
    expect($this->storage->retrieve($key))->toBeNull();
});

it('checks if token exists', function () {
    $token = 'existence-test';
    $key = 'exists-test';

    expect($this->storage->exists($key))->toBeFalse();

    $this->storage->store($token, $key);

    expect($this->storage->exists($key))->toBeTrue();
});

it('stores and retrieves last heartbeat', function () {
    $beforeTime = time();

    $this->storage->storeLastHeartbeat();

    $heartbeat = $this->storage->getLastHeartbeat();
    $afterTime = time();

    expect($heartbeat)->toBeGreaterThanOrEqual($beforeTime);
    expect($heartbeat)->toBeLessThanOrEqual($afterTime);
});

it('stores and retrieves grace period data', function () {
    $data = [
        'started_at' => now()->toIso8601String(),
        'reason' => 'test-reason',
    ];

    $this->storage->storeGracePeriodData($data);
    $retrieved = $this->storage->getGracePeriodData();

    expect($retrieved)->toBe($data);
});

it('clears all stored data', function () {
    $this->storage->store('token1', 'key1');
    $this->storage->store('token2', 'key2');
    $this->storage->storeLastHeartbeat();
    $this->storage->storeGracePeriodData(['test' => 'data']);

    $this->storage->clearAll();

    expect($this->storage->retrieve('key1'))->toBeNull();
    expect($this->storage->retrieve('key2'))->toBeNull();
    expect($this->storage->getLastHeartbeat())->toBeNull();
    expect($this->storage->getGracePeriodData())->toBeNull();
});

it('caches token when cache is enabled', function () {
    config(['licensing-client.cache.enabled' => true]);

    $token = 'cached-token';
    $key = 'cache-test';

    $this->storage->store($token, $key);

    $cacheKey = 'licensing:token:' . $key;
    $cached = Cache::store('array')->get($cacheKey);

    expect($cached)->toBe($token);
});

it('retrieves from cache first when available', function () {
    config(['licensing-client.cache.enabled' => true]);

    $token = 'cached-token';
    $key = 'cache-retrieve-test';
    $cacheKey = 'licensing:token:' . $key;

    // Store directly in cache
    Cache::store('array')->put($cacheKey, $token, 3600);

    $retrieved = $this->storage->retrieve($key);

    expect($retrieved)->toBe($token);
});