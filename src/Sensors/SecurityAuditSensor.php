<?php

namespace Laraowl\Client\Sensors;

use Illuminate\Support\Facades\File;
use Laraowl\Client\Core;
use Laraowl\Client\State\CommandState;
use Laraowl\Client\State\RequestState;

use function app;
use function base_path;
use function config;
use function count;
use function implode;
use function in_array;
use function json_decode;
use function md5;
use function md5_file;
use function now;
use function public_path;
use function sort;

/**
 * @internal
 */
final class SecurityAuditSensor
{
    /**
     * @param  Core<RequestState|CommandState>  $core
     */
    public function __construct(
        private Core $core,
    ) {
        //
    }

    public function __invoke(): void
    {
        $audit = [
            'hashes' => $this->auditFileIntegrity(),
            'environment' => $this->auditEnvironment(),
            'public_files' => $this->auditPublicFolder(),
            'dependencies' => $this->auditDependencies(),
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'os' => PHP_OS,
            ],
            'timestamp' => now()->timestamp,
        ];

        $this->core->record('security-audit', $audit);
    }

    private function auditFileIntegrity(): array
    {
        $filesToAudit = [
            '.env',
            'composer.json',
            'composer.lock',
            'public/index.php',
            'artisan',
        ];

        $hashes = [];
        foreach ($filesToAudit as $file) {
            $path = base_path($file);
            if (File::exists($path)) {
                $hashes[$file] = md5_file($path);
            }
        }

        // Recursive audit for core directories
        $dirsToAudit = ['app', 'config', 'routes'];
        foreach ($dirsToAudit as $dir) {
            $path = base_path($dir);
            if (File::isDirectory($path)) {
                $files = File::allFiles($path);
                $dirHashes = [];
                foreach ($files as $file) {
                    // Only hash PHP and config files to save time
                    if (in_array($file->getExtension(), ['php', 'json', 'env'], true)) {
                        $dirHashes[] = md5_file($file->getRealPath());
                    }
                }
                sort($dirHashes);
                $hashes[$dir] = md5(implode('', $dirHashes));
            }
        }

        return $hashes;
    }

    private function auditEnvironment(): array
    {
        return [
            'app_debug' => config('app.debug'),
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'session_secure' => config('session.secure'),
            'session_httponly' => config('session.http_only'),
            'cookie_samesite' => config('session.same_site'),
            'mailing_driver' => config('mail.default'),
            'db_connection' => config('database.default'),
        ];
    }

    private function auditPublicFolder(): array
    {
        $suspiciousFiles = [
            'phpinfo.php',
            'info.php',
            'test.php',
            'shell.php',
            '.git',
            '.svn',
            'config.php',
            'wp-config.php',
        ];

        $found = [];
        foreach ($suspiciousFiles as $file) {
            if (File::exists(public_path($file)) || File::isDirectory(public_path($file))) {
                $found[] = $file;
            }
        }

        return [
            'suspicious_files_found' => $found,
            'directory_listing_enabled' => $this->checkDirectoryListing(),
        ];
    }

    private function checkDirectoryListing(): bool
    {
        // Simple check: try to see if an empty public directory exists or if we can guess
        // This is better done from outside, but we can check if .htaccess has 'Options -Indexes'
        $htaccess = public_path('.htaccess');
        if (File::exists($htaccess)) {
            $content = File::get($htaccess);
            return str_contains($content, 'Options +Indexes') || ! str_contains($content, 'Options -Indexes');
        }
        return true; // Defaulting to true if no htaccess (might be Nginx)
    }

    private function auditDependencies(): array
    {
        $path = base_path('composer.lock');
        if (! File::exists($path)) {
            return [];
        }

        $lock = json_decode(File::get($path), true);
        $packages = [];

        foreach ($lock['packages'] ?? [] as $package) {
            $packages[$package['name']] = $package['version'];
        }

        return [
            'count' => count($packages),
            'packages' => $packages,
        ];
    }
}
