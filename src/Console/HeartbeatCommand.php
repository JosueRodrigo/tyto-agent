<?php

namespace Tyto\Agent\Console;

use Illuminate\Console\Command;
use Tyto\Agent\Core;

final class HeartbeatCommand extends Command
{
    protected $signature = 'tyto:heartbeat
        {slug : Stable lowercase identifier for the monitored process}
        {--name= : Human-readable monitor name}
        {--interval=15 : Expected interval in minutes}';

    protected $description = 'Send a process heartbeat to Tyto';

    public function handle(Core $core): int
    {
        $core->heartbeat(
            slug: (string) $this->argument('slug'),
            name: $this->option('name') ? (string) $this->option('name') : null,
            interval: (int) $this->option('interval'),
        );

        $this->components->info('Heartbeat sent.');

        return self::SUCCESS;
    }
}
