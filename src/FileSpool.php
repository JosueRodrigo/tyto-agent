<?php

namespace Tyto\Agent;

use function dirname;
use function fclose;
use function file_put_contents;
use function flock;
use function fopen;
use function ftruncate;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function preg_split;
use function stream_get_contents;

final class FileSpool
{
    public function __construct(private readonly string $path) {}

    /** @param list<array<mixed>> $records */
    public function store(array $records): void
    {
        if ($records === []) {
            return;
        }

        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }
        $payload = json_encode($records);
        if ($payload !== false) {
            @file_put_contents($this->path, $payload.PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /** @return list<list<array<mixed>>> */
    public function drain(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $handle = @fopen($this->path, 'c+');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            return [];
        }
        $contents = stream_get_contents($handle);
        ftruncate($handle, 0);
        flock($handle, LOCK_UN);
        fclose($handle);

        $batches = [];
        foreach (preg_split('/\R/', (string) $contents, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $batch = json_decode($line, true);
            if (is_array($batch)) {
                $batches[] = $batch;
            }
        }

        return $batches;
    }
}
