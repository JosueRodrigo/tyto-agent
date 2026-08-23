<?php

namespace Laraowl\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laraowl\Client\Contracts\Ingest as IngestContract;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * @internal
 */
final class HttpIngest implements IngestContract
{
    private bool $shouldDigestWhenBufferIsFull = true;

    public function __construct(
        private string $endpoint,
        private string $token,
        private float $timeout,
        public RecordsBuffer $buffer,
        private ?string $app_url = null,
    ) {
        //
    }

    public function write(array $record): void
    {
        $this->buffer->write($record);

        if ($this->shouldDigestWhenBufferIsFull && $this->buffer->full) {
            $this->digest();
        }
    }

    public function writeNow(array $record): void
    {
        $this->transmit([$record]);
    }

    public function flush(): void
    {
        $this->buffer->flush();
    }

    public function ping(): void
    {
        // Ping could be a health check or just ignored for HTTP
    }

    public function shouldDigest(bool $bool = true): void
    {
        $this->shouldDigestWhenBufferIsFull($bool);
    }

    public function shouldDigestWhenBufferIsFull(bool $bool = true): void
    {
        $this->shouldDigestWhenBufferIsFull = $bool;
    }

    public function digest(): void
    {
        $records = $this->buffer->pullRaw();

        if (empty($records)) {
            return;
        }

        $this->transmit($records);
    }

    private function transmit(array $records): void
    {
        $client = new Client([
            'base_uri' => $this->endpoint,
            'timeout'  => $this->timeout,
        ]);

        try {
            $client->post('/api/v1/ingest', [
                'headers' => [
                    'X-Tyto-Token'  => $this->token,
                    'Idempotency-Key' => Uuid::uuid4()->toString(),
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'app_url' => $this->app_url,
                    'records' => $records,
                ],
            ]);
        } catch (GuzzleException | Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Tyto Ingest Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
