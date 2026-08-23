<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

use function str_repeat;

/**
 * @internal
 */
final class ReportableHandler
{
    public ?string $reservedMemory;

    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        $this->reservedMemory = str_repeat('n', 32768);
    }

    public function __invoke(Throwable $e): void
    {
        if (HandleExceptions::$reservedMemory === null) {
            $this->reservedMemory = null;
        }

        if ($this->tyto->executionState->source === 'schedule') {
            return;
        }

        $this->tyto->report($e);
    }
}
