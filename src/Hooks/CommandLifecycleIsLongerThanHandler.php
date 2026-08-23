<?php

namespace Tyto\Agent\Hooks;

use Carbon\Carbon;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class CommandLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, InputInterface $input, int $status): void
    {
        try {
            $this->tyto->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        try {
            $this->tyto->command($input, $status);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        $this->tyto->finishExecution();
    }
}
