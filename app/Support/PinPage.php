<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Pin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PinPage
{
    /**
     * @return list<array{name: string, slug: string, latitude: float, longitude: float, find: string}>
     */
    public static function directory(): array
    {
        return array_map(function (array $city): array {
            $slug = self::slug($city['name']);

            return [
                'name' => $city['name'],
                'slug' => $slug,
                'latitude' => (float) $city['latitude'],
                'longitude' => (float) $city['longitude'],
                'find' => mb_strtolower($city['name'].' '.str_replace('-', ' ', $slug)),
            ];
        }, MapCities::all());
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float, find: string}>  $cities
     * @return list<array{name: string, slug: string, latitude: float, longitude: float, find: string, count: int}>
     */
    public static function withCounts(array $cities): array
    {
        $counts = [];
        foreach (MapCities::counts() as $row) {
            $counts[$row['name']] = $row['count'];
        }

        return array_map(function (array $city) use ($counts): array {
            $city['count'] = $counts[$city['name']] ?? 0;

            return $city;
        }, $cities);
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float}>  $cities
     * @return array{name: string, slug: string, latitude: float, longitude: float, find?: string}|null
     */
    public static function cityBySlug(array $cities, string $slug): ?array
    {
        foreach ($cities as $city) {
            if ($city['slug'] === $slug) {
                return $city;
            }
        }

        return null;
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float}>  $cities
     * @return array{name: string, slug: string, latitude: float, longitude: float, meters: float}|null
     */
    public static function cityFor(Pin $pin, array $cities): ?array
    {
        if ($pin->latitude === null || $pin->longitude === null) {
            return null;
        }

        $best = null;
        $bestMeters = MapCities::RADIUS_METERS;

        foreach ($cities as $city) {
            $meters = Pin::metersBetween(
                (float) $pin->latitude,
                (float) $pin->longitude,
                (float) $city['latitude'],
                (float) $city['longitude'],
            );

            if ($meters < $bestMeters) {
                $bestMeters = $meters;
                $best = $city;
                $best['meters'] = $meters;
            }
        }

        return $best;
    }

    public static function token(Pin $pin): string
    {
        $base = trim(Str::limit(self::slug($pin->message), 70, ''), '-');

        if ($base === '') {
            $base = 'pin';
        }

        return $base.'-'.$pin->id;
    }

    public static function path(Pin $pin, ?array $cities = null): ?string
    {
        $city = self::cityFor($pin, $cities ?? self::directory());

        if ($city === null) {
            return null;
        }

        return '/pins/'.$city['slug'].'/'.self::token($pin);
    }

    /**
     * @param  array{name: string, slug: string, latitude: float, longitude: float}  $city
     * @return list<array{pin: Pin, meters: float, path: string}>
     */
    public static function pinsInCity(array $city, ?string $category, int $km, array $cities, ?int $keepId = null): array
    {
        $boxKm = MapCities::RADIUS_METERS / 1000;
        $latPad = $boxKm / 111.32;
        $lngPad = $boxKm / (111.32 * max(cos(deg2rad($city['latitude'])), 0.2));

        $query = Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->whereBetween('latitude', [$city['latitude'] - $latPad, $city['latitude'] + $latPad])
            ->whereBetween('longitude', [$city['longitude'] - $lngPad, $city['longitude'] + $lngPad]);

        if ($category !== null) {
            $query->where('category', $category);
        }

        $limit = $km * 1000;
        $crowded = UpcomingHorizon::isCrowded($city['name']);
        $until = UpcomingHorizon::until();
        $rows = [];

        foreach ($query->get() as $pin) {
            $nearest = self::cityFor($pin, $cities);

            if ($nearest === null || $nearest['slug'] !== $city['slug'] || $nearest['meters'] > $limit) {
                continue;
            }

            if (
                $crowded
                && $pin->id !== $keepId
                && $pin->kind === Pin::KIND_UPCOMING
                && $pin->starts_at !== null
                && $pin->starts_at->gt($until)
            ) {
                continue;
            }

            $path = '/pins/'.$city['slug'].'/'.self::token($pin);
            $rows[] = [
                'pin' => $pin,
                'meters' => $nearest['meters'],
                'path' => $path,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['meters'] <=> $b['meters']);

        return $rows;
    }

    /**
     * Pages, cities, and categories. External links stay out of the XML file.
     *
     * @return list<array{title: string, links: list<array{label: string, path: string}>}>
     */
    public static function guide(): array
    {
        $pages = [
            ['label' => 'Начало', 'path' => '/'],
            ['label' => 'Картата', 'path' => '/map'],
            ['label' => 'Пинове', 'path' => '/pins'],
            ['label' => 'Changelog', 'path' => '/changelog'],
            ['label' => 'Как работи', 'path' => '/how'],
            ['label' => 'Контакт', 'path' => '/contact'],
            ['label' => 'Подкрепи ни', 'path' => '/support'],
            ['label' => 'История', 'path' => '/history'],
            ['label' => 'Карта', 'path' => '/sitemap'],
            ['label' => 'Discord', 'path' => 'https://discord.com/channels/1574875809603133562'],
        ];

        $categories = [];

        foreach (Category::listedOptions() as $slug => $name) {
            $categories[] = [
                'label' => $name,
                'path' => '/pins?'.http_build_query(['category' => $slug]),
            ];
        }

        $cities = [];

        foreach (self::directory() as $city) {
            $cities[] = [
                'label' => $city['name'],
                'path' => '/pins/'.$city['slug'],
            ];
        }

        return [
            ['title' => 'Страници', 'links' => $pages],
            ['title' => 'Градове', 'links' => $cities],
            ['title' => 'Категории', 'links' => $categories],
        ];
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    public static function sitemapUrls(string $root): array
    {
        $cities = self::directory();
        $urls = [
            ['loc' => $root.'/pins', 'lastmod' => null],
        ];

        foreach ($cities as $city) {
            $urls[] = ['loc' => $root.'/pins/'.$city['slug'], 'lastmod' => null];
        }

        Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->orderBy('id')
            ->each(function (Pin $pin) use ($cities, $root, &$urls): void {
                $path = self::path($pin, $cities);

                if ($path === null) {
                    return;
                }

                $urls[] = [
                    'loc' => $root.$path,
                    'lastmod' => $pin->updated_at?->toAtomString(),
                ];
            });

        return $urls;
    }

    /**
     * @param  array{name: string, latitude: float, longitude: float}  $city
     * @return array<string, mixed>
     */
    public static function jsonLd(Pin $pin, array $city, string $url): array
    {
        $image = $pin->imageUrl() ?: LinkPreview::logo();
        $description = trim((string) $pin->description);
        $place = [
            '@type' => 'Place',
            'name' => $city['name'],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $city['name'],
                'addressCountry' => 'BG',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => round((float) $pin->latitude, 6),
                'longitude' => round((float) $pin->longitude, 6),
            ],
        ];

        $root = (string) parse_url($url, PHP_URL_SCHEME).'://'.(string) parse_url($url, PHP_URL_HOST);
        $event = $pin->starts_at !== null || in_array($pin->category, ['event', 'sport', 'art'], true);

        if ($event) {
            $node = [
                '@type' => 'Event',
                'name' => $pin->message,
                'description' => $description !== '' ? $description : LinkPreview::FALLBACK_DESCRIPTION,
                'image' => [$image],
                'url' => $url,
                'inLanguage' => 'bg',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'startDate' => ($pin->starts_at ?? $pin->created_at)?->toAtomString(),
                'endDate' => $pin->expires_at?->toAtomString(),
                'location' => $place,
                'organizer' => [
                    '@type' => 'Organization',
                    'name' => 'Kostaa',
                    'url' => $root,
                ],
            ];
        } else {
            $node = $place;
            $node['name'] = $pin->message;
            $node['description'] = $description !== '' ? $description : LinkPreview::FALLBACK_DESCRIPTION;
            $node['image'] = $image;
            $node['url'] = $url;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $node,
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Начало', 'item' => $root.'/'],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Пинове', 'item' => $root.'/pins'],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $city['name'], 'item' => $root.'/pins/'.self::slug($city['name'])],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $pin->message, 'item' => $url],
                    ],
                ],
            ],
        ];
    }

    public static function whenLabel(Pin $pin): string
    {
        if ($pin->kind === Pin::KIND_UPCOMING && $pin->starts_at !== null && $pin->starts_at->isFuture()) {
            return self::relativeStart($pin->starts_at);
        }

        return self::timeLeft($pin->expires_at);
    }

    public static function startsSoon(Pin $pin): bool
    {
        if ($pin->starts_at === null) {
            return false;
        }

        if (! $pin->starts_at->isFuture()) {
            return $pin->expires_at === null || $pin->expires_at->isFuture();
        }

        return $pin->kind === Pin::KIND_UPCOMING
            && $pin->starts_at->lt(now()->addHours(24));
    }

    private static function relativeStart(\DateTimeInterface $start): string
    {
        $sofia = Carbon::parse($start)->timezone('Europe/Sofia');
        $today = now()->timezone('Europe/Sofia')->toDateString();
        $startDate = $sofia->toDateString();

        if ($startDate === $today) {
            $minutes = max(1, (int) round(now()->diffInMinutes($sofia, true)));

            if ($minutes < 60) {
                return 'след '.$minutes.' мин';
            }

            $hours = max(1, (int) round($minutes / 60));

            return 'след '.$hours.' '.($hours === 1 ? 'час' : 'часа');
        }

        $clock = $sofia->format('H:i');
        $days = (int) round(Carbon::parse($today, 'UTC')->diffInDays(Carbon::parse($startDate, 'UTC')));

        if ($days <= 1) {
            return 'утре в '.$clock;
        }

        return 'след '.$days.' дни в '.$clock;
    }

    public static function distanceLabel(float $meters): string
    {
        if ($meters < 1000) {
            return max(1, (int) round($meters)).' м';
        }

        $kilometers = $meters / 1000;

        if ($kilometers < 10) {
            $rounded = number_format($kilometers, 1, '.', '');

            return rtrim(rtrim($rounded, '0'), '.').' км';
        }

        return ((int) round($kilometers)).' км';
    }

    public static function timeLeft(?Carbon $expires): string
    {
        if ($expires === null || $expires->isPast()) {
            return 'Изтече';
        }

        $minutes = max(1, (int) ceil($expires->diffInSeconds(now(), true) / 60));
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        if ($days === 0) {
            if ($hours === 0) {
                return 'още '.$rest.' мин';
            }

            return $rest === 0 ? 'още '.$hours.' ч' : 'още '.$hours.' ч '.$rest.' мин';
        }

        $dayLabel = $days === 1 ? '1 ден' : $days.' дни';

        if ($hours === 0 && $rest === 0) {
            return 'още '.$dayLabel;
        }

        if ($hours === 0) {
            return 'още '.$dayLabel.' и '.$rest.' мин';
        }

        $hourLabel = $hours === 1 ? '1 час' : $hours.' часа';

        return 'още '.$dayLabel.' и '.$hourLabel;
    }

    private static function slug(string $value): string
    {
        $slug = Str::slug(Str::ascii($value));

        return $slug !== '' ? $slug : 'pin';
    }
}
