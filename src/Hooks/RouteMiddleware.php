<?php

namespace Tyto\Agent\Hooks;

use Closure;
use Illuminate\Http\Request;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RouteMiddleware
{
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
        try {
            $this->tyto->stage(ExecutionStage::Action);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        $response = $next($request);

        // If an exception occurs in the action phase, the usual
        // ResponsePrepared event is not fired. This fallback
        // ensures that we go to the AfterMiddleware stage.
        try {
            $this->tyto->stage(ExecutionStage::AfterMiddleware);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        return $response;
    }
}
