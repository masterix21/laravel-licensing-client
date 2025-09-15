<?php

namespace LucaLongo\LaravelLicensingClient\Services;

use Illuminate\Support\Facades\App;

class FingerprintGenerator
{
    /**
     * Generate a unique, stable device fingerprint
     */
    public function generate(): string
    {
        $components = [
            $this->getHostname(),
            $this->getMachineId(),
            $this->getPhpVersion(),
            $this->getLaravelVersion(),
            $this->getAppKey(),
        ];

        $fingerprint = implode('|', array_filter($components));

        return hash('sha256', $fingerprint);
    }

    /**
     * Get the hostname
     */
    protected function getHostname(): string
    {
        return gethostname() ?: 'unknown';
    }

    /**
     * Get a machine-specific identifier
     */
    protected function getMachineId(): string
    {
        // Try to get machine ID from various sources
        if (PHP_OS_FAMILY === 'Linux') {
            if (file_exists('/etc/machine-id')) {
                return trim(file_get_contents('/etc/machine-id'));
            }
            if (file_exists('/var/lib/dbus/machine-id')) {
                return trim(file_get_contents('/var/lib/dbus/machine-id'));
            }
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('ioreg -rd1 -c IOPlatformExpertDevice | grep IOPlatformUUID');
            if ($output) {
                preg_match('/"IOPlatformUUID" = "(.+?)"/', $output, $matches);
                if (isset($matches[1])) {
                    return $matches[1];
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('wmic csproduct get UUID');
            if ($output) {
                $lines = explode("\n", trim($output));
                if (isset($lines[1])) {
                    return trim($lines[1]);
                }
            }
        }

        // Fallback to MAC address
        return $this->getMacAddress();
    }

    /**
     * Get the MAC address
     */
    protected function getMacAddress(): string
    {
        $macAddress = 'unknown';

        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $output = shell_exec('ifconfig -a');

            if ($output && preg_match('/([0-9a-fA-F]{2}[:-]){5}([0-9a-fA-F]{2})/', $output, $matches)) {
                return $matches[0];
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = shell_exec('getmac');
            if ($output && preg_match('/([0-9a-fA-F]{2}-){5}([0-9a-fA-F]{2})/', $output, $matches)) {
                return $matches[0];
            }
        }

        return $macAddress;
    }

    /**
     * Get PHP version
     */
    protected function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * Get Laravel version
     */
    protected function getLaravelVersion(): string
    {
        return App::version();
    }

    /**
     * Get application key
     */
    protected function getAppKey(): string
    {
        return (string) config('app.key', 'no-app-key');
    }

    /**
     * Get additional metadata about the device
     */
    public function getMetadata(): array
    {
        return [
            'hostname' => $this->getHostname(),
            'os' => PHP_OS_FAMILY,
            'php_version' => $this->getPhpVersion(),
            'laravel_version' => $this->getLaravelVersion(),
            'environment' => App::environment(),
            'timezone' => config('app.timezone'),
        ];
    }
}