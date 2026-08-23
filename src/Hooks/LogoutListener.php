<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Auth\Events\Logout;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class LogoutListener
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Logout $event): void
    {
        try {
            if ($event->user !== null) {
                $this->tyto->remember($event->user);
            }
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
