<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class RequestHandledListener
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(RequestHandled $event): void
    {
        try {
            $this->tyto->stage(ExecutionStage::Sending);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
