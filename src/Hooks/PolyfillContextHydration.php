<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Queue\Events\JobProcessing;
use Throwable;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class PolyfillContextHydration
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(JobProcessing $event): void
    {
        try {
            $tyto = $event->job->payload()['tyto'] ?? [];

            Compatibility::$context = [
                'tyto_trace_id' => $tyto['tyto_trace_id'] ?? null,
                'tyto_should_sample' => $tyto['tyto_should_sample'] ?? null,
                'tyto_user_id' => $tyto['tyto_user_id'] ?? '',
            ];
        } catch (Throwable $e) {
            $this->tyto->report($e);

            Compatibility::$context = [];
        }
    }
}
