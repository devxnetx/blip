<?php

namespace App\Services\KupiBileti;

use Illuminate\Support\Carbon;

class ParsedEvent
{
    public function __construct(
        public string $slug,
        public string $title,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public string $url,
        public string $category,
        public ?string $description = null,
        public ?string $imageUrl = null,
        public ?string $venue = null,
        public ?string $city = null,
        public ?string $address = null,
    ) {}
}
