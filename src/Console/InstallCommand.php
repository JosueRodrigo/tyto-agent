<?php

namespace Laraowl\Client\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function base_path;
use function preg_replace;
use function str_contains;

class InstallCommand extends Command
{
    protected $signature = 'tyto:install';

    protected $aliases = ['laraowl:install'];

    protected $description = 'Install and configure the Tyto Laravel agent';

    public function handle(): int
    {
        $this->info('Installing Tyto Agent...');

        $this->publishConfiguration();

        $this->askForConfiguration();

        $this->info('Tyto Agent installed successfully.');

        return self::SUCCESS;
    }

    protected function publishConfiguration(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'tyto-config',
            '--force' => true,
        ]);
    }

    protected function askForConfiguration(): void
    {
        if (! $this->confirm('Do you want to configure your credentials now?', true)) {
            return;
        }

        $url = (string) $this->ask('Tyto Server URL', 'https://tyto.test');
        $token = (string) $this->ask('Project Token');

        if ($token === '') {
            $this->error('A project token is required.');

            return;
        }

        $this->updateEnv($url, $token);
    }

    protected function updateEnv(string $url, string $token): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);

        if (! str_contains($content, 'TYTO_SERVER_URL')) {
            $content .= "\nTYTO_SERVER_URL={$url}";
        } else {
            $content = preg_replace('/TYTO_SERVER_URL=.*/', "TYTO_SERVER_URL={$url}", $content);
        }

        if (! str_contains($content, 'TYTO_TOKEN')) {
            $content .= "\nTYTO_TOKEN={$token}";
        } else {
            $content = preg_replace('/TYTO_TOKEN=.*/', "TYTO_TOKEN={$token}", $content);
        }

        File::put($envPath, $content);

        $this->info('Environment variables updated.');
    }
}
