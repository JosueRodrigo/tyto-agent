<?php

namespace Tyto\Agent\Hooks;

use Throwable;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class PolyfillContextDehydration
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(mixed $connection, mixed $queue, array $payload): array
    {
        $context = Compatibility::$context;

        try {
            if (($context['tyto_user_id'] ?? '') === '') {
                $context['tyto_user_id'] = $this->tyto->executionState->user->resolvedUserId();
            }

            return [
                ...$payload,
                'tyto' => [
                    ...($payload['tyto'] ?? []), // @phpstan-ignore arrayUnpacking.nonIterable
                    'tyto_trace_id' => $context['tyto_trace_id'] ?? null,
                    'tyto_should_sample' => $context['tyto_should_sample'] ?? null,
                    'tyto_user_id' => $context['tyto_user_id'],
                ],
            ];
        } catch (Throwable $e) {
            $this->tyto->report($e);

            return $payload;
        }
    }
}
