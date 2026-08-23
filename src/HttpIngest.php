<?php

namespace Laraowl\Client;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Laraowl\Client\Contracts\Ingest as IngestContract;
use Ramsey\Uuid\Uuid;
use Throwable;

use function max;
use function usleep;

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
        private int $attempts = 3,
        private int $backoffMs = 100,
        private ?ClientInterface $client = null,
        private ?FileSpool $spool = null,
    ) {
        if ($this->spool === null && function_exists('app') && method_exists(app(), 'storagePath')) {
            $this->spool = new FileSpool(storage_path('framework/tyto/spool.jsonl'));
        }
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
        if (! $this->transmit([$record])) {
            $this->spool?->store([$record]);
        }
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
        if ($this->spool !== null) {
            $pending = $this->spool->drain();
            foreach ($pending as $index => $batch) {
                if (! $this->transmit($batch)) {
                    foreach (array_slice($pending, $index) as $retry) {
                        $this->spool->store($retry);
                    }
                    break;
                }
            }
        }
        $records = $this->buffer->pullRaw();

        if (empty($records)) {
            return;
        }

        if (! $this->transmit($records)) {
            $this->spool?->store($records);
        }
    }

    /**
     * @param  list<array<mixed>>  $records
     */
    private function transmit(array $records): bool
    {
        $client = $this->client ?? new Client([
            'base_uri' => $this->endpoint,
            'timeout' => $this->timeout,
        ]);

        $idempotencyKey = Uuid::uuid4()->toString();
        $attempts = max(1, $this->attempts);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client->request('POST', '/api/v1/ingest', [
                    'headers' => [
                        'X-Tyto-Token' => $this->token,
                        'Idempotency-Key' => $idempotencyKey,
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'app_url' => $this->app_url,
                        'records' => $records,
                    ],
                ]);

                return true;
            } catch (GuzzleException|Throwable $e) {
                if ($attempt < $attempts) {
                    usleep(max(0, $this->backoffMs) * $attempt * 1000);

                    continue;
                }

                error_log('Tyto Ingest Error: '.$e->getMessage());
            }
        }

        return false;
    }
}
