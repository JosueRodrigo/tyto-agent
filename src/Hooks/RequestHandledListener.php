<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Symfony\Component\HttpFoundation\Response;
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
            if ($event->response->getStatusCode() === Response::HTTP_NOT_FOUND && (bool) config('tyto.ignore.not_found', false)) {
                $this->tyto->dontSample();
                $this->tyto->ingest->flush();

                return;
            }

            $this->tyto->stage(ExecutionStage::Sending);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
