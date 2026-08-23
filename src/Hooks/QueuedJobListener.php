<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class QueuedJobListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(JobQueueing|JobQueued $event): void
    {
        try {
            $this->tyto->queuedJob($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
