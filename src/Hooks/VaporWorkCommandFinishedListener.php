<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\CommandFinished;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class VaporWorkCommandFinishedListener
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
        $this->tyto->finishExecution();
    }
}
