<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class MapCache
{
    public const SECONDS = 45;

    public const CROWDED = 'map.crowded-cities';

    public const COUNTS = 'map.city-counts';

    public static function forget(): void
    {
        Cache::forget(self::CROWDED);
        Cache::forget(self::COUNTS);
    }
}
