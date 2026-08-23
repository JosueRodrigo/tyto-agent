<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class NotificationListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(NotificationSending|NotificationSent $event): void
    {
        try {
            $this->tyto->notification($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
