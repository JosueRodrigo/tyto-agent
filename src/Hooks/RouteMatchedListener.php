<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Routing\Events\RouteMatched;
use Tyto\Agent\Core;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class RouteMatchedListener
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(RouteMatched $event): void
    {
        try {
            $this->tyto->attachMiddlewareToRoute($event->route);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
