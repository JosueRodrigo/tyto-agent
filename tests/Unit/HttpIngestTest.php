<?php

namespace Laraowl\Client\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laraowl\Client\HttpIngest;
use Laraowl\Client\RecordsBuffer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class HttpIngestTest extends TestCase
{
    public function test_it_sends_the_tyto_v1_protocol(): void
    {
        $history = [];
        $client = $this->client([new Response(202)], $history);
        $ingest = new HttpIngest(
            endpoint: 'https://tyto.example',
            token: 'project-token',
            timeout: 2.0,
            buffer: new RecordsBuffer(10),
            app_url: 'https://shop.example',
            backoffMs: 0,
            client: $client,
        );

        $ingest->writeNow(['type' => 'request', 'status' => 200]);

        self::assertCount(1, $history);
        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        self::assertSame('/api/v1/ingest', $request->getUri()->getPath());
        self::assertSame('project-token', $request->getHeaderLine('X-Tyto-Token'));
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $request->getHeaderLine('Idempotency-Key'),
        );
        self::assertSame([
            'app_url' => 'https://shop.example',
            'records' => [['type' => 'request', 'status' => 200]],
        ], json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_retries_reuse_the_same_idempotency_key(): void
    {
        $failure = new ConnectException('temporary failure', new Request('POST', '/api/v1/ingest'));
        $history = [];
        $client = $this->client([$failure, new Response(202)], $history);
        $ingest = new HttpIngest(
            endpoint: 'https://tyto.example',
            token: 'project-token',
            timeout: 2.0,
            buffer: new RecordsBuffer(10),
            attempts: 2,
            backoffMs: 0,
            client: $client,
        );

        $ingest->writeNow(['type' => 'exception']);

        self::assertCount(2, $history);
        self::assertSame(
            $history[0]['request']->getHeaderLine('Idempotency-Key'),
            $history[1]['request']->getHeaderLine('Idempotency-Key'),
        );
    }

    /**
     * @param  list<mixed>  $responses
     * @param  array<int, array<string, mixed>>  $history
     */
    private function client(array $responses, array &$history): Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }
}
