<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Console\Events\ArtisanStarting;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;

/**
 * @internal
 */
final class ArtisanStartingListener
{
    /**
     * @param  Core<CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(ArtisanStarting $event): void
    {
        try {
            $this->tyto->captureArtisan($event->artisan);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
