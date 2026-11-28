<?php

namespace App\Support;

use App\Models\Pin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SearchHints
{
    public const SOON_DAYS = 4;

    /**
     * Time words, longest first. Latin and Cyrillic both count.
     *
     * @var array<string, string>
     */
    private const WHEN = [
        'тия дни' => 'soon',
        'tiq dni' => 'soon',
        'tia dni' => 'soon',
        'tiya dni' => 'soon',
        'tomorrow' => 'tomorrow',
        'утре' => 'tomorrow',
        'utre' => 'tomorrow',
        'today' => 'today',
        'днес' => 'today',
        'dnes' => 'today',
    ];

    /** @var list<string> */
    private const FILLERS = ['във', 'в', 'vv', 'v', 'in', 'на', 'na', 'за', 'za'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function forQuery(string $query, ?string $here): array
    {
        $parsed = self::parse($query, $here);
        if ($parsed === null) {
            return [];
        }

        $counts = self::counts($parsed['city']);
        if ($parsed['when'] === null) {
            return [self::cityRow($parsed['city'], $counts)];
        }

        $rows = [self::whenRow($parsed['when'], $parsed['city'], $parsed['suggested'], $counts)];
        if ($parsed['when'] === 'soon') {
            $rows[] = self::whenRow('today', $parsed['city'], $parsed['suggested'], $counts);
            $rows[] = self::whenRow('tomorrow', $parsed['city'], $parsed['suggested'], $counts);
        }

        return $rows;
    }

    /**
     * @return array{when: ?string, city: array{name: string, latitude: float, longitude: float}, suggested: bool}|null
     */
    private static function parse(string $query, ?string $here): ?array
    {
        $rest = preg_replace('/\s+/u', ' ', mb_strtolower(trim($query))) ?? '';
        $when = null;

        foreach (self::WHEN as $phrase => $kind) {
            $pattern = '/(?<![\p{L}])'.preg_quote($phrase, '/').'(?![\p{L}])/u';
            if (! preg_match($pattern, $rest)) {
                continue;
            }
            $when = $kind;
            $rest = trim((string) preg_replace($pattern, ' ', $rest, 1));
            $rest = preg_replace('/\s+/u', ' ', $rest) ?? '';
            break;
        }

        $rest = self::stripFillers($rest);
        if ($when === null) {
            $city = self::matchCity($rest);

            return $city === null ? null : [
                'when' => null,
                'city' => $city,
                'suggested' => false,
            ];
        }

        if ($rest === '') {
            $city = self::matchCity((string) $here) ?? self::matchCity('Стара Загора');
            if ($city === null) {
                return null;
            }

            return [
                'when' => $when,
                'city' => $city,
                'suggested' => true,
            ];
        }

        $city = self::matchCity($rest);
        if ($city === null) {
            return null;
        }

        return [
            'when' => $when,
            'city' => $city,
            'suggested' => false,
        ];
    }

    private static function stripFillers(string $text): string
    {
        $rest = trim($text);
        do {
            $before = $rest;
            foreach (self::FILLERS as $filler) {
                $rest = trim((string) preg_replace(
                    '/^'.preg_quote($filler, '/').'(?![\p{L}])/u',
                    '',
                    $rest,
                ));
            }
        } while ($rest !== $before);

        return $rest;
    }

    /**
     * @return array{name: string, latitude: float, longitude: float}|null
     */
    private static function matchCity(string $text): ?array
    {
        $folded = LatinBulgarian::fold($text);
        if ($folded === '') {
            return null;
        }

        $exact = [];
        $prefix = [];
        foreach (MapCities::all() as $city) {
            $name = LatinBulgarian::fold($city['name']);
            if ($name === $folded) {
                $exact[] = $city;
            } elseif (mb_strlen($folded) >= 3 && str_starts_with($name, $folded)) {
                $prefix[] = $city;
            }
        }

        if (count($exact) === 1) {
            return $exact[0];
        }

        if ($exact === [] && count($prefix) === 1) {
            return $prefix[0];
        }

        return null;
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $city
     * @return array{today: int, tomorrow: int, soon: int, days: int}
     */
    private static function counts(array $city): array
    {
        $pins = self::pins($city);
        $sofia = now()->timezone('Europe/Sofia');

        return [
            'today' => $pins->filter(fn (Pin $pin): bool => self::overlapsDay($pin, $sofia))->count(),
            'tomorrow' => $pins->filter(fn (Pin $pin): bool => self::overlapsDay($pin, $sofia->copy()->addDay()))->count(),
            'soon' => $pins->filter(fn (Pin $pin): bool => self::startsBy($pin, now()->addDays(self::SOON_DAYS)))->count(),
            'days' => $pins->filter(fn (Pin $pin): bool => self::startsBy($pin, now()->addDays(UpcomingHorizon::DAYS)))->count(),
        ];
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $city
     * @return Collection<int, Pin>
     */
    private static function pins(array $city): Collection
    {
        $latPad = MapCities::RADIUS_METERS / 111320;
        $lngPad = MapCities::RADIUS_METERS / (111320 * max(cos(deg2rad($city['latitude'])), 0.2));

        return Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->whereBetween('latitude', [$city['latitude'] - $latPad, $city['latitude'] + $latPad])
            ->whereBetween('longitude', [$city['longitude'] - $lngPad, $city['longitude'] + $lngPad])
            ->get(['latitude', 'longitude', 'starts_at', 'created_at', 'expires_at'])
            ->filter(fn (Pin $pin): bool => Pin::metersBetween(
                (float) $pin->latitude,
                (float) $pin->longitude,
                $city['latitude'],
                $city['longitude'],
            ) <= MapCities::RADIUS_METERS)
            ->values();
    }

    private static function overlapsDay(Pin $pin, Carbon $moment): bool
    {
        [$start, $end] = Pin::sofiaDayBounds($moment);
        $begins = $pin->starts_at ?? $pin->created_at;

        return $begins !== null && $begins->lessThanOrEqualTo($end) && $pin->expires_at->greaterThan($start);
    }

    private static function startsBy(Pin $pin, Carbon $until): bool
    {
        return $pin->starts_at === null || $pin->starts_at->lessThanOrEqualTo($until);
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $city
     * @param  array{today: int, tomorrow: int, soon: int, days: int}  $counts
     * @return array<string, mixed>
     */
    private static function cityRow(array $city, array $counts): array
    {
        return [
            'kind' => 'city',
            'city' => $city['name'],
            'latitude' => $city['latitude'],
            'longitude' => $city['longitude'],
            'suggested' => false,
            'today' => $counts['today'],
            'tomorrow' => $counts['tomorrow'],
            'days' => $counts['days'],
            'span' => UpcomingHorizon::DAYS,
        ];
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $city
     * @param  array{today: int, tomorrow: int, soon: int, days: int}  $counts
     * @return array<string, mixed>
     */
    private static function whenRow(string $kind, array $city, bool $suggested, array $counts): array
    {
        return [
            'kind' => $kind,
            'city' => $city['name'],
            'latitude' => $city['latitude'],
            'longitude' => $city['longitude'],
            'suggested' => $suggested,
            'count' => $counts[$kind],
            'span' => $kind === 'soon' ? self::SOON_DAYS : null,
        ];
    }
}
