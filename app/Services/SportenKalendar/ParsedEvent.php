<?php

namespace App\Services\SportenKalendar;

use Illuminate\Support\Carbon;

class ParsedEvent
{
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $sportSlug,
        public ?string $sportName,
        public Carbon $startsAt,
        public Carbon $endsAt,
        public ?string $address,
        public ?string $imageUrl,
        public string $url,
    ) {}
}
