<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Throwable;

/**
 * @internal
 */
final class ScheduledTaskListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): void
    {
        // We report the exception here because the scheduler handles it after the task has finished and the data is ingested.
        // This ensures that the exception is captured in the scheduled task record.
        if ($event instanceof ScheduledTaskFailed) {
            $this->tyto->report($event->exception);
        }

        if ($this->isFinishedEventForFailedTask($event)) {
            return;
        }

        if ($event instanceof ScheduledTaskSkipped) {
            $this->tyto->prepareForScheduledTask($event->task);
        }

        try {
            $this->tyto->scheduledTask($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        $this->tyto->finishExecution()->waitForExecution();
    }

    private function isFinishedEventForFailedTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): bool
    {
        return Compatibility::$firesFinishedAndFailedEventsForScheduledConsoleCommands &&
            $event instanceof ScheduledTaskFinished &&
            $event->task->command !== null &&
            $event->task->exitCode !== 0 &&
            ! $event->task->runInBackground;
    }
}
