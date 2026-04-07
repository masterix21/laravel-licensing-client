<?php

namespace LucaLongo\LaravelLicensingClient\Commands;

use Illuminate\Console\Command;
use LucaLongo\LaravelLicensingClient\LaravelLicensingClient;

class LicenseInfoCommand extends Command
{
    protected $signature = 'license:info {key? : The license key to get info for}';

    protected $description = 'Display license information';

    public function handle(LaravelLicensingClient $client): int
    {
        $licenseKey = $this->argument('key') ?? config('licensing-client.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Fetching license information...');

        try {
            $licenseInfo = $client->getLicenseInfo($licenseKey);

            if (empty($licenseInfo)) {
                $this->error('No license information available. Please activate the license first.');

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('License Information:');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['License Key', substr($licenseKey, 0, 8).'...'],
                    ['Status', $licenseInfo['status'] ?? 'N/A'],
                    ['Issued At', $licenseInfo['issued_at'] ?? 'N/A'],
                    ['Token Expires At', $licenseInfo['expires_at'] ?? 'Never'],
                    ['License Expires At', $licenseInfo['license_expires_at'] ?? 'Never'],
                    ['Max Usages', $licenseInfo['max_usages'] ?? 'Unlimited'],
                    ['Force Online After', $licenseInfo['force_online_after'] ?? 'N/A'],
                    ['Grace Until', $licenseInfo['grace_until'] ?? 'N/A'],
                ]
            );

            if ($client->isExpiringSoon(7, $licenseKey)) {
                $this->newLine();
                $this->warn('Warning: License is expiring soon!');
            }

            if ($client->requiresOnlineRefresh($licenseKey)) {
                $this->newLine();
                $this->warn('Warning: Online refresh is required!');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get license info: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
