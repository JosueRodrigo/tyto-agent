<?php

namespace Tyto\Agent\Hooks;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class GuzzleMiddleware
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    /**
     * TODO record the failed responses as well.
     */
    public function __invoke(callable $handler): callable
    {
        if ($this->tyto->config['filtering']['ignore_outgoing_requests'] || $this->tyto->paused()) {
            return $handler;
        }

        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            try {
                $startMicrotime = $this->tyto->clock->microtime();
            } catch (Throwable $e) {
                $this->tyto->report($e, handled: true);

                return $handler($request, $options);
            }

            return $handler($request, $options)->then(function (ResponseInterface $response) use ($request, $startMicrotime): ResponseInterface {
                try {
                    $endMicrotime = $this->tyto->clock->microtime();

                    $this->tyto->outgoingRequest(
                        $startMicrotime, $endMicrotime,
                        $request, $response,
                    );
                } catch (Throwable $e) {
                    $this->tyto->report($e, handled: true);
                }

                return $response;
            });
        };
    }
}
