<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Routing\Events\PreparingResponse;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class PreparingResponseListener
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(PreparingResponse $event): void
    {
        try {
            if ($this->tyto->executionStageIs(ExecutionStage::Action)) {
                $this->tyto->stage(ExecutionStage::Render);
            }
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
