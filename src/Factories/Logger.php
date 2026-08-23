<?php

namespace Tyto\Agent\Factories;

use DateTimeZone;
use Monolog\Logger as Monolog;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Tyto\Agent\Core;
use Tyto\Agent\Hooks\LogHandler;
use Tyto\Agent\Hooks\LogRecordProcessor;
use Tyto\Agent\State\CommandState;
use Tyto\Agent\State\RequestState;

/**
 * @internal
 */
final class Logger
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
     * @param  array<string, mixed>&array{level: \Psr\Log\LogLevel::*}  $config
     */
    public function __invoke(array $config): LoggerInterface
    {
        return new Monolog(
            name: 'tyto',
            handlers: [
                new LogHandler(
                    tyto: $this->tyto,
                    level: Monolog::toMonologLevel($config['level']),
                    // There is some unexpected behaviour in the framework when
                    // using a log stack that causes monolog processors to leak
                    // and apply their side-effects to other log handlers in
                    // the stack. Instead of passing processors to the monolog
                    // instance, as you would usually expect, we pass them to
                    // our handler to apply manually. This allows us to keep
                    // the side-effects of the processors isolated to
                    // TytoAgent's handler when used in a stack of handlers.
                    processors: [
                        new LogRecordProcessor($this->tyto, 'Y-m-d H:i:s.uP'),
                        new PsrLogMessageProcessor('Y-m-d H:i:s.uP'),
                    ],
                ),
            ],
            timezone: new DateTimeZone('UTC'),
        );
    }
}
