<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\ScheduledTaskStarting;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class ScheduledTaskStartingListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(ScheduledTaskStarting $event): void
    {
        try {
            $this->tyto->prepareForScheduledTask($event->task);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
