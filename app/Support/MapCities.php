<?php

namespace App\Support;

use App\Models\Pin;
use Illuminate\Support\Facades\Cache;

class MapCities
{
    public const RADIUS_METERS = 18000;

    /**
     * The cities on the map. A pin counts for the nearest one inside the radius.
     *
     * @return array<int, array{name: string, latitude: float, longitude: float}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'София', 'latitude' => 42.6977, 'longitude' => 23.3219],
            ['name' => 'Пловдив', 'latitude' => 42.1354, 'longitude' => 24.7453],
            ['name' => 'Варна', 'latitude' => 43.2141, 'longitude' => 27.9147],
            ['name' => 'Бургас', 'latitude' => 42.5048, 'longitude' => 27.4626],
            ['name' => 'Стара Загора', 'latitude' => 42.4258, 'longitude' => 25.6345],
            ['name' => 'Русе', 'latitude' => 43.8356, 'longitude' => 25.9657],
            ['name' => 'Плевен', 'latitude' => 43.417, 'longitude' => 24.6067],
            ['name' => 'Сливен', 'latitude' => 42.6816, 'longitude' => 26.3292],
            ['name' => 'Добрич', 'latitude' => 43.5726, 'longitude' => 27.8273],
            ['name' => 'Шумен', 'latitude' => 43.2712, 'longitude' => 26.9361],
            ['name' => 'Перник', 'latitude' => 42.6052, 'longitude' => 23.0378],
            ['name' => 'Хасково', 'latitude' => 41.9344, 'longitude' => 25.5554],
            ['name' => 'Ямбол', 'latitude' => 42.4842, 'longitude' => 26.5035],
            ['name' => 'Пазарджик', 'latitude' => 42.1928, 'longitude' => 24.3336],
            ['name' => 'Благоевград', 'latitude' => 42.0119, 'longitude' => 23.09],
            ['name' => 'Велико Търново', 'latitude' => 43.0757, 'longitude' => 25.6172],
            ['name' => 'Враца', 'latitude' => 43.2102, 'longitude' => 23.5529],
            ['name' => 'Габрово', 'latitude' => 42.8742, 'longitude' => 25.3187],
            ['name' => 'Видин', 'latitude' => 43.9916, 'longitude' => 22.8824],
            ['name' => 'Монтана', 'latitude' => 43.4125, 'longitude' => 23.225],
            ['name' => 'Ловеч', 'latitude' => 43.137, 'longitude' => 24.7142],
            ['name' => 'Кърджали', 'latitude' => 41.6338, 'longitude' => 25.3777],
            ['name' => 'Кюстендил', 'latitude' => 42.2839, 'longitude' => 22.6911],
            ['name' => 'Разград', 'latitude' => 43.5332, 'longitude' => 26.5242],
            ['name' => 'Силистра', 'latitude' => 44.1147, 'longitude' => 27.2672],
            ['name' => 'Търговище', 'latitude' => 43.2494, 'longitude' => 26.5727],
            ['name' => 'Смолян', 'latitude' => 41.5774, 'longitude' => 24.7129],
            ['name' => 'Казанлък', 'latitude' => 42.6194, 'longitude' => 25.393],
        ];
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public static function counts(): array
    {
        return Cache::remember(MapCache::COUNTS, MapCache::SECONDS, fn (): array => self::countsNow());
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private static function countsNow(): array
    {
        $cities = self::all();
        $counts = array_fill_keys(array_column($cities, 'name'), 0);

        Pin::query()
            ->onMap()
            ->get(['latitude', 'longitude'])
            ->each(function (Pin $pin) use ($cities, &$counts): void {
                $nearest = null;
                $nearestMeters = self::RADIUS_METERS;

                foreach ($cities as $city) {
                    $meters = Pin::metersBetween(
                        (float) $pin->latitude,
                        (float) $pin->longitude,
                        $city['latitude'],
                        $city['longitude'],
                    );

                    if ($meters < $nearestMeters) {
                        $nearestMeters = $meters;
                        $nearest = $city['name'];
                    }
                }

                if ($nearest !== null) {
                    $counts[$nearest]++;
                }
            });

        return array_map(
            fn (array $city): array => ['name' => $city['name'], 'count' => $counts[$city['name']]],
            $cities,
        );
    }
}
