<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Database\Events\QueryExecuted;
use Throwable;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class QueryExecutedListener
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(QueryExecuted $event): void
    {
        try {
            $this->tyto->query($event);
        } catch (Throwable $e) {
            $this->tyto->report($e, handled: true);
        }
    }
}
