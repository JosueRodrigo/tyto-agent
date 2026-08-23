<?php

namespace Tyto\Agent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tyto\Agent\Core;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\State\RequestState;
use Throwable;

final class Sample
{
    /**
     * @param  Core<RequestState>  $tyto
     */
    public function __construct(private Core $tyto)
    {
        //
    }

    public static function rate(float $rate): string
    {
        $rate = (string) $rate;

        if ($rate === '0') {
            $rate = '0.0';
        }

        return self::class.':'.$rate;
    }

    public static function always(): string
    {
        return self::class.':1.0';
    }

    public static function never(): string
    {
        return self::class.':0.0';
    }

    public function handle(Request $request, Closure $next, float $rate): mixed
    {
        try {
            $this->tyto->sample($rate);
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);
        }

        return $next($request);
    }
}
