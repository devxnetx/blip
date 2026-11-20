<?php

namespace App\Services;

use RuntimeException;

class ImportHalted extends RuntimeException
{
    /**
     * @param  array{upcoming?: int, current?: int, unassigned?: int, skipped?: int, hidden?: int}  $counts
     */
    public function __construct(string $message, public readonly array $counts = [])
    {
        parent::__construct($message);
    }
}
