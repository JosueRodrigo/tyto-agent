<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class WorkerLifecycleListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Looping|JobPopping|JobProcessing|WorkerStopping|CommandFinished $event): void
    {
        try {
            match ($event::class) {
                Looping::class, WorkerStopping::class => $this->tyto->finishExecution()->waitForExecution(),
                CommandFinished::class => $event->command === 'queue:work' && $this->tyto->finishExecution()->waitForExecution(),
                JobPopping::class => $this->tyto->prepareForNextJob(),
                JobProcessing::class => $this->tyto->prepareForJob($event->job),
            };
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
