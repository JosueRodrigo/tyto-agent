<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Foundation\Events\Terminating;
use Throwable;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class TerminatingListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Terminating $event): void
    {
        if (! Compatibility::$terminatingEventExists) {
            return;
        }

        try {
            $this->tyto->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
