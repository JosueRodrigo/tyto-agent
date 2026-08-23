<?php

namespace Tyto\Agent;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\ForgettingKey;
use Illuminate\Cache\Events\KeyForgetFailed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWriteFailed;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Cache\Events\RetrievingKey;
use Illuminate\Cache\Events\RetrievingManyKeys;
use Illuminate\Cache\Events\WritingKey;
use Illuminate\Cache\Events\WritingManyKeys;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobQueueing;
use Illuminate\Queue\Queue;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Livewire\LivewireManager;
use Ramsey\Uuid\Uuid as BaseUuid;
use Throwable;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\Factories\Logger;
use Tyto\Agent\Hooks\ArtisanStartingListener;
use Tyto\Agent\Hooks\CacheEventListener;
use Tyto\Agent\Hooks\CommandBootedHandler;
use Tyto\Agent\Hooks\CommandStartingListener;
use Tyto\Agent\Hooks\ContextDehydratingHandler;
use Tyto\Agent\Hooks\CreateQueuePayloadHandler;
use Tyto\Agent\Hooks\ExceptionHandlerResolvedHandler;
use Tyto\Agent\Hooks\GlobalMiddleware;
use Tyto\Agent\Hooks\HttpClientFactoryResolvedHandler;
use Tyto\Agent\Hooks\HttpKernelResolvedHandler;
use Tyto\Agent\Hooks\LivewireListener;
use Tyto\Agent\Hooks\LogoutListener;
use Tyto\Agent\Hooks\MailListener;
use Tyto\Agent\Hooks\NotificationListener;
use Tyto\Agent\Hooks\OctaneListener;
use Tyto\Agent\Hooks\PolyfillContextDehydration;
use Tyto\Agent\Hooks\PolyfillContextHydration;
use Tyto\Agent\Hooks\PreparingResponseListener;
use Tyto\Agent\Hooks\QueryExecutedListener;
use Tyto\Agent\Hooks\QueuedJobListener;
use Tyto\Agent\Hooks\RequestBootedHandler;
use Tyto\Agent\Hooks\RequestHandledListener;
use Tyto\Agent\Hooks\ResponsePreparedListener;
use Tyto\Agent\Hooks\RouteMatchedListener;
use Tyto\Agent\Hooks\RouteMiddleware;
use Tyto\Agent\Hooks\TerminatingListener;
use Tyto\Agent\Http\Middleware\Sample;
use Tyto\Agent\Sensors\SecurityAuditSensor;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;
use Tyto\Agent\Support\Uuid;

use function array_filter;
use function array_keys;
use function array_values;
use function class_exists;
use function defined;
use function max;
use function microtime;
use function min;

/**
 * @internal
 */
final class TytoAgentServiceProvider extends ServiceProvider
{
    /**
     * @var Core<RequestState|CommandState>
     */
    private Core $core;

    private float $timestamp;

    private bool $isRequest;

    private Repository $config;

    /**
     * @var array{
     *     enabled?: bool,
     *     sampling?: array{
     *        requests?: float,
     *        commands?: float,
     *        exceptions?: float,
     *        tasks?: float,
     *     },
     *     ignore?: array{
     *         cache?: bool,
     *         mail?: bool,
     *         notifications?: bool,
     *         outgoing_requests?: bool,
     *         queries?: bool,
     *     },
     *     token?: string,
     *     server_url?: string,
     *     environment?: array{ deploy_id?: string, server_name?: string },
     *     heartbeat?: array{ enabled?: bool, slug?: string, name?: string, interval?: int },
     *     privacy?: array{
     *         capture_source_code?: bool,
     *         capture_payload?: bool,
     *         redact_fields?: string[],
     *         redact_headers?: string[],
     *     },
     *     ingest?: array{ timeout?: float|int, buffer_size?: int },
     *  }
     */
    private array $tytoConfig;

    private ?Throwable $registerException = null;

    public function register(): void
    {
        try {
            $this->captureTimestamp();
            Compatibility::boot($this->app);
            $this->captureExecutionType();
            $this->registerAndCaptureConfig();
            $this->registerBindings();

            if (! $this->core->enabled()) {
                return;
            }

            $this->registerHooks();
        } catch (Throwable $e) {
            $this->registerException = $e;
        }
    }

    public function boot(): void
    {
        try {
            if ($this->registerException) {
                $this->handleAndClearRegisterException();

                return;
            }

            if ($this->app->runningInConsole()) {
                $this->registerPublications();
                $this->registerCommands();
                $this->registerSchedule();
            }
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);
        }
    }

    private function captureTimestamp(): void
    {
        $this->timestamp = match (true) {
            defined('LARAVEL_START') => LARAVEL_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        };
    }

    private function captureExecutionType(): void
    {
        $this->isRequest = ! $this->app->runningInConsole() || Env::get('TYTO_FORCE_REQUEST');
    }

    private function registerAndCaptureConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tyto.php', 'tyto');

        $this->config = $this->app->make(Repository::class);

        $this->tytoConfig = $this->config->get('tyto') ?? []; // @phpstan-ignore assign.propertyType
    }

    private function registerBindings(): void
    {
        $this->registerLogger();
        $this->registerMiddleware();
        $this->buildAndRegisterCore();
    }

    private function registerLogger(): void
    {
        if (! $this->config->has('logging.channels.tyto')) {
            $this->config->set('logging.channels.tyto', [
                'driver' => 'custom',
                'via' => Logger::class,
                'level' => 'debug',
            ]);
        }

        $this->app->singleton(Logger::class, fn () => new Logger($this->core));
    }

    private function registerMiddleware(): void
    {
        $this->app->singleton(RouteMiddleware::class, fn () => new RouteMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->scoped(GlobalMiddleware::class, fn () => new GlobalMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->singleton(Sample::class, fn () => new Sample($this->core)); // @phpstan-ignore argument.type
    }

    private function buildAndRegisterCore(): void
    {
        $clock = new Clock;
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());
        $executionState = $this->executionState($uuid->make());

        $this->app->instance(Core::class, $this->core = new Core(
            ingest: new HttpIngest(
                endpoint: $this->tytoConfig['server_url'] ?? 'https://tyto.test',
                token: $this->tytoConfig['token'] ?? '',
                timeout: $this->tytoConfig['ingest']['timeout'] ?? 2.0,
                buffer: new RecordsBuffer(
                    length: $this->tytoConfig['ingest']['buffer_size'] ?? 500,
                ),
                app_url: $this->config->get('app.url'),
                attempts: $this->tytoConfig['ingest']['attempts'] ?? 3,
                backoffMs: $this->tytoConfig['ingest']['backoff_ms'] ?? 100,
            ),
            sensor: new SensorManager(
                executionState: $executionState,
                clock: $clock = new Clock,
                location: new Location(
                    basePath: $this->app->basePath(),
                    publicPath: $this->app->publicPath(),
                ),
                captureExceptionSourceCode: (bool) ($this->tytoConfig['privacy']['capture_source_code'] ?? true),
                captureRequestPayload: (bool) ($this->tytoConfig['privacy']['capture_payload'] ?? false),
                redactPayloadFields: $this->tytoConfig['privacy']['redact_fields'] ?? ['_token', 'password', 'password_confirmation'],
                redactHeaders: $this->tytoConfig['privacy']['redact_headers'] ?? ['Authorization', 'Cookie', 'Proxy-Authorization', 'X-XSRF-TOKEN'],
                config: $this->config,
            ),
            executionState: $executionState,
            clock: $clock,
            uuid: $uuid,
            config: [
                'enabled' => $this->tytoConfig['enabled'] ?? true,
                'sampling' => [
                    'requests' => $this->tytoConfig['sampling']['requests'] ?? 1.0,
                    'commands' => $this->tytoConfig['sampling']['commands'] ?? 1.0,
                    'exceptions' => $this->tytoConfig['sampling']['exceptions'] ?? 1.0,
                    'scheduled_tasks' => $this->tytoConfig['sampling']['tasks'] ?? 1.0,
                ],
                'filtering' => [
                    'ignore_cache_events' => (bool) ($this->tytoConfig['ignore']['cache'] ?? false),
                    'ignore_mail' => (bool) ($this->tytoConfig['ignore']['mail'] ?? false),
                    'ignore_notifications' => (bool) ($this->tytoConfig['ignore']['notifications'] ?? false),
                    'ignore_outgoing_requests' => (bool) ($this->tytoConfig['ignore']['outgoing_requests'] ?? false),
                    'ignore_queries' => (bool) ($this->tytoConfig['ignore']['queries'] ?? false),
                ],
            ],
        ));
    }

    private function handleAndClearRegisterException(): void
    {
        TytoAgent::unrecoverableExceptionOccurred($this->registerException); // @phpstan-ignore argument.type

        $this->registerException = null;
    }

    private function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/tyto.php' => $this->app->configPath('tyto.php'),
        ], ['tyto', 'tyto-config']);
    }

    private function registerCommands(): void
    {
        $this->commands([
            Console\HeartbeatCommand::class,
            Console\InstallCommand::class,
        ]);
    }

    private function registerSchedule(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $schedule->call(new SecurityAuditSensor($this->core))->hourly();

            $heartbeat = $this->tytoConfig['heartbeat'] ?? [];

            if ($heartbeat['enabled'] ?? true) {
                $interval = max(1, min(59, (int) ($heartbeat['interval'] ?? 1)));

                $schedule->call(fn () => $this->core->heartbeat(
                    slug: (string) ($heartbeat['slug'] ?? 'scheduler'),
                    name: (string) ($heartbeat['name'] ?? 'Laravel scheduler'),
                    interval: $interval,
                ))
                    ->name('tyto:scheduler-heartbeat')
                    ->cron("*/{$interval} * * * *")
                    ->withoutOverlapping();
            }
        });
    }

    private function registerHooks(): void
    {
        $core = $this->core;

        /** @var Dispatcher */
        $events = $this->app->make(Dispatcher::class);

        //
        // -------------------------------------------------------------------------
        // Sensor hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Tyto\Agent\Records\Query
         */
        $events->listen(QueryExecuted::class, (new QueryExecutedListener($core))(...));

        /**
         * @see \Tyto\Agent\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($core))(...));

        /**
         * @see \Tyto\Agent\Records\QueuedJob
         */
        $events->listen([JobQueueing::class, JobQueued::class], (new QueuedJobListener($core))(...));

        /**
         * @see \Tyto\Agent\Records\Notification
         */
        $events->listen([NotificationSending::class, NotificationSent::class], (new NotificationListener($core))(...));

        /**
         * @see \Tyto\Agent\Records\Mail
         */
        $events->listen([MessageSending::class, MessageSent::class], (new MailListener($core))(...));

        /**
         * @see \Tyto\Agent\Records\OutgoingRequest
         */
        $this->callAfterResolving(Http::class, (new HttpClientFactoryResolvedHandler($core))(...));

        /**
         * @see \Tyto\Agent\Records\CacheEvent
         */
        $events->listen([
            RetrievingKey::class,
            RetrievingManyKeys::class,
            CacheHit::class,
            CacheMissed::class,
            WritingKey::class,
            WritingManyKeys::class,
            KeyWritten::class,
            KeyWriteFailed::class,
            ForgettingKey::class,
            KeyForgotten::class,
            KeyForgetFailed::class,
        ], (new CacheEventListener($core))(...));

        $events->listen(RequestReceived::class, (new OctaneListener($core))(...)); // @phpstan-ignore class.notFound

        Queue::createPayloadUsing(new CreateQueuePayloadHandler($core));

        if (Compatibility::$contextExists) {
            Context::dehydrating(new ContextDehydratingHandler($core));
        } else {
            Queue::createPayloadUsing(new PolyfillContextDehydration($core));
            $events->listen((new PolyfillContextHydration($core))(...));
        }

        //
        // -------------------------------------------------------------------------
        // Execution stage hooks
        // --------------------------------------------------------------------------
        //

        if ($this->isRequest) {
            /** @var Core<RequestState> $core */
            $this->registerRequestHooks($events, $core);
        } else {
            /** @var Core<CommandState> $core */
            $this->registerConsoleHooks($events, $core);
        }

        /** @var Core<RequestState|CommandState> $core */

        /**
         * @see \Tyto\Agent\ExecutionStage::Terminating
         */
        $events->listen(Terminating::class, (new TerminatingListener($core))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerRequestHooks(Dispatcher $events, Core $core): void
    {
        // TODO resolve the kernel inline rather than in the listener.

        /**
         * @see \Tyto\Agent\State\RequestState::$user
         *
         * TODO handle this on the queue
         */
        $events->listen(Logout::class, (new LogoutListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted((new RequestBootedHandler($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::Action
         * @see \Tyto\Agent\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, (new RouteMatchedListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, (new PreparingResponseListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, (new ResponsePreparedListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, (new RequestHandledListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::End
         * @see \Tyto\Agent\Records\Request
         * @see \Tyto\Agent\ExecutionStage::Terminating
         * @see \Tyto\Agent\Core::finishExecution()
         */
        $this->callAfterResolving(HttpKernelContract::class, (new HttpKernelResolvedHandler($core))(...));

        $this->registerLivewireHooks($core);
    }

    /**
     * @param  Core<CommandState>  $core
     */
    private function registerConsoleHooks(Dispatcher $events, Core $core): void
    {
        /** @var ConsoleKernelContract */
        $kernel = $this->app->make(ConsoleKernelContract::class);

        /**
         * @see \Tyto\Agent\State\CommandState::$artisan
         */
        $events->listen(ArtisanStarting::class, (new ArtisanStartingListener($core))(...));

        /**
         * @see \Tyto\Agent\ExecutionStage::Action
         */
        $this->app->booted((new CommandBootedHandler($core))(...));

        /**
         * @see \Tyto\Agent\State\CommandState::$name
         *
         * Commands...
         * @see \Tyto\Agent\ExecutionStage::Terminating
         * @see \Tyto\Agent\ExecutionStage::End
         * @see \Tyto\Agent\Records\Command
         * @see \Tyto\Agent\Core::finishExecution()
         *
         * Jobs...
         * @see \Tyto\Agent\State\CommandState::$source
         * @see \Tyto\Agent\State\CommandState::flush()
         * @see \Tyto\Agent\State\CommandState::$timestamp
         * @see \Tyto\Agent\State\CommandState::$id
         * @see \Tyto\Agent\Records\JobAttempt
         * @see \Tyto\Agent\Records\Exception
         *
         * Scheduled tasks...
         * @see \Tyto\Agent\Core::finishExecution()
         */
        $events->listen(CommandStarting::class, (new CommandStartingListener($events, $core, $kernel))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerLivewireHooks(Core $core): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->app->booted(static function ($app) use ($core) {
            if (! $app->bound(LivewireManager::class)) {
                return;
            }

            $listener = new LivewireListener($core);

            // Livewire 2
            Livewire::listen('component.hydrate.subsequent', $listener->componentHydrateSubsequent(...));

            // Livewire 3
            Livewire::listen('hydrate', $listener->hydrate(...));
        });
    }

    private function executionState(string $trace): RequestState|CommandState
    {
        Compatibility::addTraceIdToContext($trace);

        if ($this->isRequest) {
            return new RequestState(
                timestamp: $this->timestamp,
                trace: $trace,
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->tytoConfig['environment']['deploy_id'] ?? '',
                server: $this->tytoConfig['environment']['server_name'] ?? '',
                user: $this->userProvider(),
            );
        } else {
            return new CommandState(
                timestamp: $this->timestamp,
                trace: new LazyValue(function () {
                    return (string) Compatibility::getTraceIdFromContext(function () { // @phpstan-ignore cast.string
                        $trace = $this->core->uuid->make();

                        Compatibility::addTraceIdToContext($trace);

                        return $trace;
                    });
                }),
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->tytoConfig['environment']['deploy_id'] ?? '',
                server: $this->tytoConfig['environment']['server_name'] ?? '',
                user: $this->userProvider(),
            );
        }
    }

    private function userProvider(): UserProvider
    {
        /** @var AuthManager */
        $auth = $this->app->make(AuthManager::class);

        return new UserProvider(
            fn (callable $callback) => $this->core->ignore(static fn () => $callback($auth)),
            fn () => $this->core->userDetailsResolver,
            fn () => $this->core->report(...),
            fn () => $this->guardNames(),
        );
    }

    /**
     * Every auth guard the application defines, inspected when identifying the
     * user behind a request.
     *
     * @return list<string>
     */
    private function guardNames(): array
    {
        /** @var array<string, mixed> $guards */
        $guards = $this->config->get('auth.guards', []);

        return array_values(array_filter(array_keys($guards), 'is_string'));
    }
}
