<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Throwable;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class CommandFinishedListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(CommandFinished $event): void
    {
        try {
            if ($this->tyto->capturingCommandNamed($event->command) && ! Compatibility::$terminatingEventExists) {
                $this->tyto->stage(ExecutionStage::Terminating);
            }
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
