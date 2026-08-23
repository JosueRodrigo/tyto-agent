<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Routing\Events\ResponsePrepared;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class ResponsePreparedListener
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(ResponsePrepared $event): void
    {
        try {
            if ($this->tyto->executionStageIs(ExecutionStage::Render)) {
                $this->tyto->stage(ExecutionStage::AfterMiddleware);
            }
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
