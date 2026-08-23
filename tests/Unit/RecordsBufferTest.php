<?php

namespace Tyto\Agent\Tests\Unit;

use Tyto\Agent\RecordsBuffer;
use PHPUnit\Framework\TestCase;

final class RecordsBufferTest extends TestCase
{
    public function test_it_marks_the_buffer_full_and_keeps_the_latest_records(): void
    {
        $buffer = new RecordsBuffer(2);

        $buffer->write(['id' => 1]);
        $buffer->write(['id' => 2]);

        self::assertTrue($buffer->full);

        $buffer->write(['id' => 3]);

        self::assertSame([['id' => 2], ['id' => 3]], $buffer->pullRaw());
        self::assertFalse($buffer->full);
        self::assertCount(0, $buffer);
    }

    public function test_an_empty_pull_returns_a_valid_empty_payload(): void
    {
        $payload = (new RecordsBuffer(10))->pull('token-hash');

        self::assertTrue($payload->isEmpty());
        self::assertSame('16:v1:token-hash:[]', $payload->pull());
    }
}
