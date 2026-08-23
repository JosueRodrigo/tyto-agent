<?php

namespace Tyto\Agent\Hooks;

use Illuminate\Log\Context\Repository;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class ContextDehydratingHandler
{
    /**
     * @param  Core<RequestState|CommandState>  $tyto
     */
    public function __construct(
        private Core $tyto,
    ) {
        //
    }

    public function __invoke(Repository $context): void
    {
        try {
            if (($context->getHidden('tyto_user_id') ?? '') === '') {
                $context->addHidden('tyto_user_id', $this->tyto->executionState->user->resolvedUserId());
            }
        } catch (Throwable $e) {
            $this->tyto->report($e);
        }
    }
}
