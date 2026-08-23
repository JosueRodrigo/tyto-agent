<?php

namespace Tyto\Agent\Hooks;

use Tyto\Agent\Core;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Throwable;

/**
 * @internal
 */
final class CreateQueuePayloadHandler
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
        try {
            return [
                ...$payload,
                'tyto' => [
                    ...($payload['tyto'] ?? []),  // @phpstan-ignore arrayUnpacking.nonIterable
                    'job_id' => $this->tyto->uuid->make(),
                ],
            ];
        } catch (Throwable $e) {
            $this->tyto->report($e);

            return $payload;
        }
    }
}
