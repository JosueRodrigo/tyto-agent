<?php

use Tyto\Agent\Compatibility;

it('stores and clears tyto context for long-lived workers', function () {
    Compatibility::$contextExists = false;
    Compatibility::$context = [];

    Compatibility::addTraceIdToContext('trace-parent');
    Compatibility::addSamplingToContext(true);
    Compatibility::addUserIdToContext('user-42');

    expect(Compatibility::getTraceIdFromContext())->toBe('trace-parent')
        ->and(Compatibility::getSamplingFromContext(null))->toBeTrue()
        ->and(Compatibility::getUserIdFromContext())->toBe('user-42');

    Compatibility::clearTytoContext();

    expect(Compatibility::getTraceIdFromContext())->toBeNull()
        ->and(Compatibility::getSamplingFromContext(null))->toBeNull()
        ->and(Compatibility::getUserIdFromContext())->toBe('');
});
