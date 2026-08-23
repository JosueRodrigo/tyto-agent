<?php

namespace Laraowl\Client;

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
use Laraowl\Client\Facades\LaraowlClient;
use Laraowl\Client\Factories\Logger;
use Laraowl\Client\Hooks\ArtisanStartingListener;
use Laraowl\Client\Hooks\CacheEventListener;
use Laraowl\Client\Hooks\CommandBootedHandler;
use Laraowl\Client\Hooks\CommandStartingListener;
use Laraowl\Client\Hooks\ContextDehydratingHandler;
use Laraowl\Client\Hooks\CreateQueuePayloadHandler;
use Laraowl\Client\Hooks\ExceptionHandlerResolvedHandler;
use Laraowl\Client\Hooks\GlobalMiddleware;
use Laraowl\Client\Hooks\HttpClientFactoryResolvedHandler;
use Laraowl\Client\Hooks\HttpKernelResolvedHandler;
use Laraowl\Client\Hooks\LivewireListener;
use Laraowl\Client\Hooks\LogoutListener;
use Laraowl\Client\Hooks\MailListener;
use Laraowl\Client\Hooks\NotificationListener;
use Laraowl\Client\Hooks\OctaneListener;
use Laraowl\Client\Hooks\PolyfillContextDehydration;
use Laraowl\Client\Hooks\PolyfillContextHydration;
use Laraowl\Client\Hooks\PreparingResponseListener;
use Laraowl\Client\Hooks\QueryExecutedListener;
use Laraowl\Client\Hooks\QueuedJobListener;
use Laraowl\Client\Hooks\RequestBootedHandler;
use Laraowl\Client\Hooks\RequestHandledListener;
use Laraowl\Client\Hooks\ResponsePreparedListener;
use Laraowl\Client\Hooks\RouteMatchedListener;
use Laraowl\Client\Hooks\RouteMiddleware;
use Laraowl\Client\Hooks\TerminatingListener;
use Laraowl\Client\Http\Middleware\Sample;
use Laraowl\Client\Sensors\SecurityAuditSensor;
use Laraowl\Client\State\CommandState;
use Laraowl\Client\State\RequestState;
use Laraowl\Client\Support\Uuid;
use Laravel\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Livewire\LivewireManager;
use Ramsey\Uuid\Uuid as BaseUuid;
use Throwable;

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
final class LaraowlClientServiceProvider extends ServiceProvider
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
    private array $laraowlConfig;

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
            LaraowlClient::unrecoverableExceptionOccurred($e);
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
        $this->isRequest = ! $this->app->runningInConsole() || Env::get('TYTO_FORCE_REQUEST') || Env::get('LARAOWL_FORCE_REQUEST');
    }

    private function registerAndCaptureConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laraowl.php', 'laraowl');
        $this->mergeConfigFrom(__DIR__.'/../config/tyto.php', 'tyto');

        $this->config = $this->app->make(Repository::class);

        $this->laraowlConfig = $this->config->get('tyto') ?? []; // @phpstan-ignore assign.propertyType
    }

    private function registerBindings(): void
    {
        $this->registerLogger();
        $this->registerMiddleware();
        $this->buildAndRegisterCore();
    }

    private function registerLogger(): void
    {
        if (! $this->config->has('logging.channels.laraowl')) {
            $this->config->set('logging.channels.laraowl', [
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
                endpoint: $this->laraowlConfig['server_url'] ?? 'https://tyto.test',
                token: $this->laraowlConfig['token'] ?? '',
                timeout: $this->laraowlConfig['ingest']['timeout'] ?? 2.0,
                buffer: new RecordsBuffer(
                    length: $this->laraowlConfig['ingest']['buffer_size'] ?? 500,
                ),
                app_url: $this->config->get('app.url'),
                attempts: $this->laraowlConfig['ingest']['attempts'] ?? 3,
                backoffMs: $this->laraowlConfig['ingest']['backoff_ms'] ?? 100,
            ),
            sensor: new SensorManager(
                executionState: $executionState,
                clock: $clock = new Clock,
                location: new Location(
                    basePath: $this->app->basePath(),
                    publicPath: $this->app->publicPath(),
                ),
                captureExceptionSourceCode: (bool) ($this->laraowlConfig['privacy']['capture_source_code'] ?? true),
                captureRequestPayload: (bool) ($this->laraowlConfig['privacy']['capture_payload'] ?? false),
                redactPayloadFields: $this->laraowlConfig['privacy']['redact_fields'] ?? ['_token', 'password', 'password_confirmation'],
                redactHeaders: $this->laraowlConfig['privacy']['redact_headers'] ?? ['Authorization', 'Cookie', 'Proxy-Authorization', 'X-XSRF-TOKEN'],
                config: $this->config,
            ),
            executionState: $executionState,
            clock: $clock,
            uuid: $uuid,
            config: [
                'enabled' => $this->laraowlConfig['enabled'] ?? true,
                'sampling' => [
                    'requests' => $this->laraowlConfig['sampling']['requests'] ?? 1.0,
                    'commands' => $this->laraowlConfig['sampling']['commands'] ?? 1.0,
                    'exceptions' => $this->laraowlConfig['sampling']['exceptions'] ?? 1.0,
                    'scheduled_tasks' => $this->laraowlConfig['sampling']['tasks'] ?? 1.0,
                ],
                'filtering' => [
                    'ignore_cache_events' => (bool) ($this->laraowlConfig['ignore']['cache'] ?? false),
                    'ignore_mail' => (bool) ($this->laraowlConfig['ignore']['mail'] ?? false),
                    'ignore_notifications' => (bool) ($this->laraowlConfig['ignore']['notifications'] ?? false),
                    'ignore_outgoing_requests' => (bool) ($this->laraowlConfig['ignore']['outgoing_requests'] ?? false),
                    'ignore_queries' => (bool) ($this->laraowlConfig['ignore']['queries'] ?? false),
                ],
            ],
        ));
    }

    private function handleAndClearRegisterException(): void
    {
        LaraowlClient::unrecoverableExceptionOccurred($this->registerException); // @phpstan-ignore argument.type

        $this->registerException = null;
    }

    private function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/tyto.php' => $this->app->configPath('tyto.php'),
        ], ['tyto', 'tyto-config']);

        $this->publishes([
            __DIR__.'/../config/laraowl.php' => $this->app->configPath('laraowl.php'),
        ], ['laraowl', 'laraowl-config']);
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

            $heartbeat = $this->laraowlConfig['heartbeat'] ?? [];

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
         * @see \Laraowl\Client\Records\Query
         */
        $events->listen(QueryExecuted::class, (new QueryExecutedListener($core))(...));

        /**
         * @see \Laraowl\Client\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($core))(...));

        /**
         * @see \Laraowl\Client\Records\QueuedJob
         */
        $events->listen([JobQueueing::class, JobQueued::class], (new QueuedJobListener($core))(...));

        /**
         * @see \Laraowl\Client\Records\Notification
         */
        $events->listen([NotificationSending::class, NotificationSent::class], (new NotificationListener($core))(...));

        /**
         * @see \Laraowl\Client\Records\Mail
         */
        $events->listen([MessageSending::class, MessageSent::class], (new MailListener($core))(...));

        /**
         * @see \Laraowl\Client\Records\OutgoingRequest
         */
        $this->callAfterResolving(Http::class, (new HttpClientFactoryResolvedHandler($core))(...));

        /**
         * @see \Laraowl\Client\Records\CacheEvent
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
         * @see \Laraowl\Client\ExecutionStage::Terminating
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
         * @see \Laraowl\Client\State\RequestState::$user
         *
         * TODO handle this on the queue
         */
        $events->listen(Logout::class, (new LogoutListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted((new RequestBootedHandler($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::Action
         * @see \Laraowl\Client\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, (new RouteMatchedListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, (new PreparingResponseListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, (new ResponsePreparedListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, (new RequestHandledListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::End
         * @see \Laraowl\Client\Records\Request
         * @see \Laraowl\Client\ExecutionStage::Terminating
         * @see \Laraowl\Client\Core::finishExecution()
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
         * @see \Laraowl\Client\State\CommandState::$artisan
         */
        $events->listen(ArtisanStarting::class, (new ArtisanStartingListener($core))(...));

        /**
         * @see \Laraowl\Client\ExecutionStage::Action
         */
        $this->app->booted((new CommandBootedHandler($core))(...));

        /**
         * @see \Laraowl\Client\State\CommandState::$name
         *
         * Commands...
         * @see \Laraowl\Client\ExecutionStage::Terminating
         * @see \Laraowl\Client\ExecutionStage::End
         * @see \Laraowl\Client\Records\Command
         * @see \Laraowl\Client\Core::finishExecution()
         *
         * Jobs...
         * @see \Laraowl\Client\State\CommandState::$source
         * @see \Laraowl\Client\State\CommandState::flush()
         * @see \Laraowl\Client\State\CommandState::$timestamp
         * @see \Laraowl\Client\State\CommandState::$id
         * @see \Laraowl\Client\Records\JobAttempt
         * @see \Laraowl\Client\Records\Exception
         *
         * Scheduled tasks...
         * @see \Laraowl\Client\Core::finishExecution()
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
                deploy: $this->laraowlConfig['environment']['deploy_id'] ?? '',
                server: $this->laraowlConfig['environment']['server_name'] ?? '',
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
                deploy: $this->laraowlConfig['environment']['deploy_id'] ?? '',
                server: $this->laraowlConfig['environment']['server_name'] ?? '',
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
