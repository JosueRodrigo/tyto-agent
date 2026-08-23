<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Http\Client\Factory;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class HttpClientFactoryResolvedHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Factory $factory): void
    {
        try {
            /**
             * @see \Tyto\Agent\Records\OutgoingRequest
             */
            $factory->globalMiddleware($this->tyto->guzzleMiddleware());
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
