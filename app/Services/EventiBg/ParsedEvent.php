<?php

namespace App\Services\EventiBg;

use Illuminate\Support\Carbon;

class ParsedEvent
{
    public function __construct(
        public string $slug,
        public string $title,
        public ?float $latitude,
        public ?float $longitude,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public string $url,
        public ?string $description = null,
        public ?string $imageUrl = null,
    ) {}
}
