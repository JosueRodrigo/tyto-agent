<?php

namespace Tyto\Agent\Hooks;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Tyto\Agent\Core;
use Tyto\Agent\ExecutionStage;
use Tyto\Agent\State\RequestState;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @internal
 */
final class RequestLifecycleIsLongerThanHandler
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Carbon $startedAt, Request $request, Response $response): void
    {
        try {
            $this->tyto->stage(ExecutionStage::End);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        try {
            $this->tyto->captureUser();
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        try {
            $this->tyto->request($request, $response);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }

        $this->tyto->finishExecution();
    }
}
