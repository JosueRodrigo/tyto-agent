<?php

namespace Tyto\Agent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function base_path;
use function preg_replace;
use function str_contains;

class InstallCommand extends Command
{
    protected $signature = 'tyto:install';

    protected $description = 'Install and configure the Tyto Laravel agent';

    public function handle(): int
    {
        $this->info('Installing Tyto Agent...');

        $this->publishConfiguration();

        $this->askForConfiguration();

        $this->info('Tyto Agent installed successfully.');
        $this->displayLoggingInstructions();

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

    protected function displayLoggingInstructions(): void
    {
        $this->newLine();
        $this->components->info('Enable Tyto application logs');
        $this->line('The agent registers the <comment>tyto</comment> logging channel automatically.');
        $this->line('Add it to Laravel\'s active stack in your <comment>.env</comment>:');
        $this->newLine();
        $this->line('  <comment>LOG_CHANNEL=stack</comment>');
        $this->line('  <comment>LOG_STACK=single,tyto</comment>');
        $this->newLine();
        $this->line('If config/logging.php uses a fixed stack, add <comment>\'tyto\'</comment> to its channels array.');
        $this->line('Then run <comment>php artisan optimize:clear</comment> before testing a log entry.');
    }
}
