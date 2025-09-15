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

        if (!$licenseKey) {
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

            $this->info('');
            $this->info('🌟 License Information:');
            $this->info('');

            $this->table(
                ['Property', 'Value'],
                [
                    ['License Key', substr($licenseKey, 0, 8) . '...'],
                    ['Customer Name', $licenseInfo['customer_name'] ?? 'N/A'],
                    ['Customer Email', $licenseInfo['customer_email'] ?? 'N/A'],
                    ['Issued At', $licenseInfo['issued_at'] ?? 'N/A'],
                    ['Expires At', $licenseInfo['expires_at'] ?? 'Never'],
                    ['Max Usages', $licenseInfo['max_usages'] ?? 'Unlimited'],
                    ['Current Usages', $licenseInfo['current_usages'] ?? '0'],
                ]
            );

            if (!empty($licenseInfo['features'])) {
                $this->info('');
                $this->info('Features:');
                foreach ($licenseInfo['features'] as $feature) {
                    $this->info("  ✓ {$feature}");
                }
            }

            if ($client->isExpiringSoon(7, $licenseKey)) {
                $this->warn('');
                $this->warn('⚠ License is expiring soon!');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to get license info: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}