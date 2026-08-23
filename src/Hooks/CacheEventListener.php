<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Cache\Events\CacheEvent;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class CacheEventListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(CacheEvent $event): void
    {
        try {
            $this->tyto->cacheEvent($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
