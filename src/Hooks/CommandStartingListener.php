<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Tyto\Agent\Core;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class CommandStartingListener
{
    private bool $hasRun = false;

    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Dispatcher $events,
        private Core $tyto,
        private ConsoleKernelContract $kernel,
    ) {
        //
    }

    public function __invoke(CommandStarting $event): void
    {
        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;

        try {
            match ($event->command) {
                'queue:work', 'queue:listen', 'horizon:work', 'vapor:work' => $this->registerJobHooks($event),
                'schedule:run', 'schedule:work' => $this->registerScheduledTaskHooks(),
                'help', 'inspire', 'schedule:finish' => null,
                default => $this->registerCommandHooks($event),
            };
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);
        }
    }

    private function registerJobHooks(CommandStarting $event): void
    {
        $this->tyto->configureForJobs();

        /**
         * @see \Tyto\Agent\Core::finishExecution()
         * @see \Tyto\Agent\State\CommandState::flush()
         * @see \Tyto\Agent\State\CommandState::$timestamp
         * @see \Tyto\Agent\State\CommandState::$id
         */
        $this->events->listen([
            Looping::class,
            JobPopping::class,
            JobProcessing::class,
            WorkerStopping::class,
            CommandFinished::class,
        ], (new WorkerLifecycleListener($this->tyto))(...));

        /**
         * @see \Tyto\Agent\Records\JobAttempt
         * @see \Tyto\Agent\Core::finishExecution()
         */
        $this->events->listen([
            JobProcessed::class,
            JobReleasedAfterException::class,
            JobFailed::class,
        ], (new JobAttemptListener($this->tyto))(...));

        if ($event->command === 'vapor:work') {
            $this->events->listen(CommandFinished::class, (new VaporWorkCommandFinishedListener($this->tyto))(...));
        }
    }

    private function registerScheduledTaskHooks(): void
    {
        $this->tyto->configureForScheduledTasks();

        $this->events->listen(ScheduledTaskStarting::class, (new ScheduledTaskStartingListener($this->tyto))(...));

        /**
         * @see \Tyto\Agent\Core::finishExecution()
         */
        $this->events->listen([
            ScheduledTaskFinished::class,
            ScheduledTaskSkipped::class,
            ScheduledTaskFailed::class,
        ], (new ScheduledTaskListener($this->tyto))(...));
    }

    private function registerCommandHooks(CommandStarting $event): void
    {
        if (! $this->kernel instanceof ConsoleKernel) {
            return;
        }

        $this->tyto->configureCommandSampling($event->command);

        $this->tyto->prepareForCommand($event->command);

        /**
         * @see \Tyto\Agent\ExecutionStage::Terminating
         */
        $this->events->listen(CommandFinished::class, (new CommandFinishedListener($this->tyto))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::End
         * @see \Tyto\Agent\Records\Command
         * @see \Tyto\Agent\Core::finishExecution()
         */
        $this->kernel->whenCommandLifecycleIsLongerThan(-1, new CommandLifecycleIsLongerThanHandler($this->tyto));
    }
}
