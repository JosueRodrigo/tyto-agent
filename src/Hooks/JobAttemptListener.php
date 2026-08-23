<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobReleasedAfterException;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class JobAttemptListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(JobProcessed|JobReleasedAfterException|JobFailed $event): void
    {
        try {
            $this->tyto->jobAttempt($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
