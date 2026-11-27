<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Pin;

class PartnerEvents
{
    /**
     * @var array<string, array{name: string, detail: string}>
     */
    public const TIMES = [
        'now' => [
            'name' => 'Сега',
            'detail' => 'Събитието е започнало и още не е свършило.',
        ],
        'today' => [
            'name' => 'Днес',
            'detail' => 'Прозорецът му застъпва днешния ден по Европа/София.',
        ],
        '4d' => [
            'name' => 'Тия дни',
            'detail' => 'Започва до 4 дни. Вече започналите също влизат.',
        ],
        '10d' => [
            'name' => '10 дни',
            'detail' => 'Започва до 10 дни. Вече започналите също влизат.',
        ],
        'all' => [
            'name' => 'Всичко',
            'detail' => 'Всяко събитие, което още не е изтекло. Това е стойността, ако time липсва.',
        ],
    ];

    /**
     * @return list<array{slug: string, name: string, latitude: float, longitude: float}>
     */
    public static function cities(): array
    {
        return array_map(fn (array $city): array => [
            'slug' => $city['slug'],
            'name' => $city['name'],
            'latitude' => $city['latitude'],
            'longitude' => $city['longitude'],
        ], PinPage::directory());
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public static function types(): array
    {
        $types = [];

        foreach (Category::options() as $slug => $name) {
            $types[] = ['slug' => $slug, 'name' => $name];
        }

        return $types;
    }

    /**
     * @return list<array{id: string, name: string, detail: string}>
     */
    public static function times(): array
    {
        $times = [];

        foreach (self::TIMES as $id => $time) {
            $times[] = [
                'id' => $id,
                'name' => $time['name'],
                'detail' => $time['detail'],
            ];
        }

        return $times;
    }

    /**
     * @param  array{city: ?string, type: ?string, time: string, page: int, limit: int}  $filters
     * @return array{data: list<array<string, mixed>>, page: int, limit: int, total: int}
     */
    public static function events(array $filters): array
    {
        $cities = PinPage::directory();
        $city = $filters['city'] === null
            ? null
            : PinPage::cityBySlug($cities, $filters['city']);
        $query = Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->whereNotNull('starts_at');

        if ($filters['type'] !== null) {
            $query->where('category', $filters['type']);
        }

        $until = match ($filters['time']) {
            '4d' => now()->addDays(4),
            '10d' => now()->addDays(10),
            default => null,
        };

        if ($filters['time'] === 'now') {
            $query->where('starts_at', '<=', now());
        } elseif ($filters['time'] === 'today') {
            $query->occursToday();
        } elseif ($until !== null) {
            $query->where('starts_at', '<=', $until);
        }

        if ($city !== null) {
            $boxKm = MapCities::RADIUS_METERS / 1000;
            $latPad = $boxKm / 111.32;
            $lngPad = $boxKm / (111.32 * max(cos(deg2rad($city['latitude'])), 0.2));
            $query->whereBetween('latitude', [$city['latitude'] - $latPad, $city['latitude'] + $latPad])
                ->whereBetween('longitude', [$city['longitude'] - $lngPad, $city['longitude'] + $lngPad]);
        }

        $rows = $query->orderBy('starts_at')->orderBy('id')->limit(1000)->get();

        if ($city !== null) {
            $rows = $rows->filter(function (Pin $pin) use ($cities, $city): bool {
                $found = PinPage::cityFor($pin, $cities);

                return $found !== null && $found['slug'] === $city['slug'];
            })->values();
        }

        $total = $rows->count();
        $page = $filters['page'];
        $limit = $filters['limit'];

        return [
            'data' => $rows
                ->slice(($page - 1) * $limit, $limit)
                ->map(fn (Pin $pin): array => self::payload($pin, $cities))
                ->values()
                ->all(),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float, find?: string}>  $cities
     * @return array<string, mixed>
     */
    private static function payload(Pin $pin, array $cities): array
    {
        $city = PinPage::cityFor($pin, $cities);
        $path = $city === null ? null : '/pins/'.$city['slug'].'/'.PinPage::token($pin);

        return [
            'id' => $pin->id,
            'title' => $pin->message,
            'description' => $pin->description,
            'type' => $pin->category,
            'city' => $city['slug'] ?? null,
            'city_name' => $city['name'] ?? null,
            'latitude' => $pin->latitude,
            'longitude' => $pin->longitude,
            'starts_at' => $pin->starts_at?->toIso8601String(),
            'ends_at' => $pin->expires_at->toIso8601String(),
            'image_url' => $pin->imageUrl(),
            'url' => $path === null ? null : url($path),
        ];
    }
}
