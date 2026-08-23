<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class CommandBootedHandler
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->tyto->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
