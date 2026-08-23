<?php

namespace Tyto\Agent\Hooks;

use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Laravel\Octane\Events\RequestReceived;
use Throwable;

/**
 * @internal
 */
final class OctaneListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(private Core $tyto)
    {
        //
    }

    public function __invoke(RequestReceived $event): void // @phpstan-ignore class.notFound
    {
        try {
            $this->tyto->prepareForNextRequest();
        } catch (Throwable $e) {
            $this->tyto->report($e);
        }
    }
}
