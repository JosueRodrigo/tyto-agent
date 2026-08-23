<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class ExceptionHandlerResolvedHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(ExceptionHandler $handler): void
    {
        try {
            if ($handler instanceof Handler) {
                /**
                 * @see \Tyto\Agent\Records\Exception
                 */
                $handler->reportable(new ReportableHandler($this->tyto));
            }
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
