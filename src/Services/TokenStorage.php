<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use LucaLongo\LaravelLicensingClient\Exceptions\LicensingException;

class TokenStorage
{
    protected string $storagePath;

    public function __construct()
    {
        $this->storagePath = config('licensing-client.storage_path', storage_path('app/licensing'));
        $this->ensureStorageDirectoryExists();
    }

    /**
     * Store a license token
     */
    public function store(string $token, ?string $key = 'default'): void
    {
        try {
            $filename = $this->getTokenFilename($key);
            $encryptedToken = encrypt($token);

            File::put($filename, $encryptedToken);

            // Also cache it if caching is enabled
            if (config('licensing-client.cache.enabled')) {
                Cache::store(config('licensing-client.cache.store'))
                    ->put(
                        $this->getCacheKey($key),
                        $token,
                        config('licensing-client.cache.ttl')
                    );
            }
        } catch (\Exception $e) {
            throw LicensingException::tokenStorageFailed($e->getMessage());
        }
    }

    /**
     * Retrieve a stored license token
     */
    public function retrieve(?string $key = 'default'): ?string
    {
        // Try cache first if enabled
        if (config('licensing-client.cache.enabled')) {
            $cached = Cache::store(config('licensing-client.cache.store'))
                ->get($this->getCacheKey($key));

            if ($cached) {
                return $cached;
            }
        }

        // Fall back to file storage
        $filename = $this->getTokenFilename($key);

        if (! File::exists($filename)) {
            return null;
        }

        try {
            $encryptedToken = File::get($filename);
            $token = decrypt($encryptedToken);

            // Re-cache it if caching is enabled
            if (config('licensing-client.cache.enabled')) {
                Cache::store(config('licensing-client.cache.store'))
                    ->put(
                        $this->getCacheKey($key),
                        $token,
                        config('licensing-client.cache.ttl')
                    );
            }

            return $token;
        } catch (\Exception $e) {
            throw LicensingException::invalidToken();
        }
    }

    /**
     * Delete a stored token
     */
    public function delete(?string $key = 'default'): void
    {
        $filename = $this->getTokenFilename($key);

        if (File::exists($filename)) {
            File::delete($filename);
        }

        // Clear from cache
        if (config('licensing-client.cache.enabled')) {
            Cache::store(config('licensing-client.cache.store'))
                ->forget($this->getCacheKey($key));
        }
    }

    /**
     * Check if a token exists
     */
    public function exists(?string $key = 'default'): bool
    {
        return File::exists($this->getTokenFilename($key));
    }

    /**
     * Store last heartbeat timestamp
     */
    public function storeLastHeartbeat(): void
    {
        $filename = $this->storagePath.'/last_heartbeat';
        File::put($filename, (string) time());
    }

    /**
     * Get last heartbeat timestamp
     */
    public function getLastHeartbeat(): ?int
    {
        $filename = $this->storagePath.'/last_heartbeat';

        if (! File::exists($filename)) {
            return null;
        }

        return (int) File::get($filename);
    }

    /**
     * Store grace period data
     */
    public function storeGracePeriodData(array $data): void
    {
        $filename = $this->storagePath.'/grace_period.json';
        File::put($filename, json_encode($data));
    }

    /**
     * Get grace period data
     */
    public function getGracePeriodData(): ?array
    {
        $filename = $this->storagePath.'/grace_period.json';

        if (! File::exists($filename)) {
            return null;
        }

        return json_decode(File::get($filename), true);
    }

    /**
     * Clear all stored data
     */
    public function clearAll(): void
    {
        if (File::isDirectory($this->storagePath)) {
            File::deleteDirectory($this->storagePath);
            $this->ensureStorageDirectoryExists();
        }

        // Clear all cache entries
        if (config('licensing-client.cache.enabled')) {
            Cache::store(config('licensing-client.cache.store'))
                ->flush();
        }
    }

    /**
     * Ensure the storage directory exists
     */
    protected function ensureStorageDirectoryExists(): void
    {
        if (! File::isDirectory($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0755, true);
        }
    }

    /**
     * Get the filename for a token
     */
    protected function getTokenFilename(string $key): string
    {
        return $this->storagePath.'/'.hash('sha256', $key).'.token';
    }

    /**
     * Get the cache key for a token
     */
    protected function getCacheKey(string $key): string
    {
        return 'licensing:token:'.$key;
    }
}
