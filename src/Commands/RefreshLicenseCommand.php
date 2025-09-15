<?php

namespace LucaLongo\LaravelLicensingClient\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class RefreshLicenseCommand extends Command
{
    protected $signature = 'license:refresh {key? : The license key to refresh}';

    protected $description = 'Refresh a license token';

    public function handle(LaravelLicensingClient $client): int
    {
        $licenseKey = $this->argument('key') ?? config('licensing-client.license_key');

        if (!$licenseKey) {
            $this->error('License key is required');
            return self::FAILURE;
        }

        $this->info('Refreshing license token...');

        try {
            if ($client->refresh($licenseKey)) {
                $this->info('License token refreshed successfully!');
                return self::SUCCESS;
            }

            $this->error('Failed to refresh license token');
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Token refresh failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}