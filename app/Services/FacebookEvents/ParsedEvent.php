<?php

namespace App\Services\FacebookEvents;

use Illuminate\Support\Carbon;

class ParsedEvent
{
    public function __construct(
        public string $id,
        public string $title,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public string $url,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public ?string $venue = null,
        public ?string $address = null,
        public ?string $cityId = null,
        public ?string $cityLabel = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $canceled = false,
        public bool $online = false,
    ) {}
}
