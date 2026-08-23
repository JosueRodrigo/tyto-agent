<?php

namespace Tyto\Agent\Records;

use Tyto\Agent\QueryConnectionType;

final class Query
{
    public function __construct(
        public string $sql,
        public readonly string $file,
        public readonly int $line,
        public readonly int $duration,
        public readonly string $connection,
        public readonly QueryConnectionType $connectionType,
    ) {
        //
    }
}
