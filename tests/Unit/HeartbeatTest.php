<?php

namespace Laraowl\Client\Tests\Unit;

use InvalidArgumentException;
use Laraowl\Client\Clock;
use Laraowl\Client\Contracts\Ingest;
use Laraowl\Client\Core;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class HeartbeatTest extends TestCase
{
    public function test_it_sends_a_versioned_heartbeat_record(): void
    {
        $ingest = new HeartbeatIngest;
        $clock = new Clock;
        $clock->microtimeResolver = static fn () => 1234.5;
        $core = (new ReflectionClass(Core::class))->newInstanceWithoutConstructor();
        $core->ingest = $ingest;
        $core->clock = $clock;

        $core->heartbeat('nightly-import', 'Nightly import', 1440);

        self::assertSame([[
            't' => 'heartbeat',
            'v' => 1,
            'timestamp' => 1234.5,
            'payload' => [
                'slug' => 'nightly-import',
                'name' => 'Nightly import',
                'interval' => 1440,
            ],
        ]], $ingest->records);
    }

    public function test_it_rejects_invalid_heartbeat_configuration(): void
    {
        $core = (new ReflectionClass(Core::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $core->heartbeat('Invalid Slug', interval: 0);
    }
}

final class HeartbeatIngest implements Ingest
{
    /**
     * @var list<array<mixed>>
     */
    public array $records = [];

    public function write(array $record): void
    {
        $this->records[] = $record;
    }

    public function writeNow(array $record): void
    {
        $this->records[] = $record;
    }

    public function ping(): void {}

    public function shouldDigest(bool $bool = true): void {}

    public function shouldDigestWhenBufferIsFull(bool $bool = true): void {}

    public function digest(): void {}

    public function flush(): void {}
}
