<?php

namespace Tyto\Agent\Hooks;

use Closure;
use Illuminate\Http\Request;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class GlobalMiddleware
{
    private bool $hasHandledRequest = false;

    private bool $hasTerminated = false;

    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->hasHandledRequest) {
            return $next($request);
        }

        $this->hasHandledRequest = true;

        try {
            $this->tyto->configureRequestSampling();
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);
        }

        try {
            $this->tyto->captureRequestPreview($request);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($this->hasTerminated || Compatibility::$terminatingEventExists) {
            return;
        }

        $this->hasTerminated = true;

        try {
            $this->tyto->stage(ExecutionStage::Terminating);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
