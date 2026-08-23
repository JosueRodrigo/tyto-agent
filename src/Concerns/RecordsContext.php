<?php

namespace Tyto\Agent\Concerns;

use Illuminate\Support\Facades\Context;
use Throwable;
use Tyto\Agent\Compatibility;
use Tyto\Agent\Facades\TytoAgent;
use Tyto\Agent\Types\Str;

use function json_encode;

/**
 * @internal
 */
trait RecordsContext
{
    private function serializedContext(): string
    {
        if (! Compatibility::$contextExists) {
            return '';
        }

        try {
            return Str::text(json_encode((object) Context::all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        } catch (Throwable $e) {
            TytoAgent::unrecoverableExceptionOccurred($e);

            return '{"_tyto_error":"Failed to serialize context"}';
        }
    }
}
