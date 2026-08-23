<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Kernel;
use Tyto\Agent\Core;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\Http\Middleware\Sample;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class HttpKernelResolvedHandler
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(KernelContract $kernel, Application $app): void
    {
        if (! $kernel instanceof Kernel) {
            return;
        }

        try {
            /**
             * @see \Tyto\Agent\ExecutionStage::End
             * @see \Tyto\Agent\Records\Request
             * @see \Tyto\Agent\Core::finishExecution()
             */
            $kernel->whenRequestLifecycleIsLongerThan(-1, new RequestLifecycleIsLongerThanHandler($this->tyto));
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);
        }

        try {
            /**
             * @see \Tyto\Agent\ExecutionStage::Terminating
             */
            $kernel->prependMiddleware(GlobalMiddleware::class);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        try {
            $kernel->prependToMiddlewarePriority(Sample::class);
        } catch (Throwable $e) {
            $this->tyto->report($e);
        }
    }
}
