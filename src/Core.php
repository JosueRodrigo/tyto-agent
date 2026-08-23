<?php

namespace Laraowl\Client;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laraowl\Client\Contracts\Ingest;
use Laraowl\Client\Facades\LaraowlClient;
use Laraowl\Client\Hooks\GuzzleMiddleware;
use Laraowl\Client\State\CommandState;
use Laraowl\Client\State\RequestState;
use Laraowl\Client\Support\Uuid;
use Throwable;
use WeakMap;

use function preg_match;
use function str_replace;
use function trim;
use function ucfirst;

/**
 * @template TState of RequestState|CommandState
 */
final class Core
{
    use Concerns\CapturesState,
        Concerns\RedactsRecords,
        Concerns\RejectsRecords;

    /**
     * @internal
     *
     * @var null|(callable(Authenticatable): array{id: mixed, name?: mixed, username?: mixed})
     */
    public $userDetailsResolver = null;

    /**
     * @param  TState  $executionState
     * @param  array{
     *     enabled: bool,
     *     sampling: array{
     *         requests: float,
     *         commands: float,
     *         exceptions: float,
     *         scheduled_tasks: float,
     *     },
     *     filtering: array{
     *         ignore_cache_events: bool,
     *         ignore_mail: bool,
     *         ignore_notifications: bool,
     *         ignore_outgoing_requests: bool,
     *         ignore_queries: bool,
     *     },
     * }  $config
     */
    public function __construct(
        /** @internal */
        public Ingest $ingest,
        /** @internal */
        public SensorManager $sensor,
        /** @internal */
        public RequestState|CommandState $executionState,
        /** @internal */
        public Clock $clock,
        /** @internal */
        public Uuid $uuid,
        /** @internal */
        public array $config,
    ) {
        $this->routesWithMiddlewareRegistered = new WeakMap;
        $this->scheduledTasksSampleRates = new WeakMap;
    }

    /**
     * @api
     */
    public function user(callable $callback): void
    {
        $this->userDetailsResolver = $callback;
    }

    /**
     * @api
     */
    public function guzzleMiddleware(): callable
    {
        return new GuzzleMiddleware($this);
    }

    /**
     * @api
     */
    public function digest(): void
    {
        $this->finishExecution();
    }

    /**
     * @internal
     *
     * @return $this
     */
    public function finishExecution(): self
    {
        try {
            if ($this->sampling) {
                $this->ingest->digest();
            } else {
                $this->ingest->flush();
            }
        } catch (Throwable $e) {
            LaraowlClient::unrecoverableExceptionOccurred($e);
        }

        return $this;
    }

    /**
     * @api
     */
    public function record(string $type, array $payload): void
    {
        $this->ingest->writeNow([
            't' => $type,
            'v' => 1,
            'timestamp' => $this->clock->microtime(),
            'payload' => $payload,
        ]);
    }

    /**
     * Report that a recurring process completed successfully.
     *
     * @api
     */
    public function heartbeat(string $slug, ?string $name = null, int $interval = 15): void
    {
        $slug = trim($slug);

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,99}$/', $slug) !== 1) {
            throw new InvalidArgumentException('Heartbeat slug must contain only lowercase letters, numbers, dots, dashes, or underscores.');
        }

        if ($interval < 1 || $interval > 10080) {
            throw new InvalidArgumentException('Heartbeat interval must be between 1 and 10080 minutes.');
        }

        $this->record('heartbeat', [
            'slug' => $slug,
            'name' => $name ?: ucfirst(str_replace(['-', '_', '.'], ' ', $slug)),
            'interval' => $interval,
        ]);
    }

    /**
     * @internal
     */
    public function enabled(): bool
    {
        return $this->config['enabled'];
    }
}
