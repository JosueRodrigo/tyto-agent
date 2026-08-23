<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Contracts\Foundation\Application;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RequestBootedHandler
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Application $app): void
    {
        try {
            $this->tyto->stage(ExecutionStage::BeforeMiddleware);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
