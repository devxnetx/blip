<?php

namespace App\Services\KupiBileti;

use App\Services\SportenKalendar\NominatimGeocoder;
use App\Support\ShowLength;
use Illuminate\Support\Carbon;

class EventPage
{
    public static function parse(string $html, string $url): ?ParsedEvent
    {
        $nodes = self::nodes($html);
        $event = null;

        foreach ($nodes as $node) {
            if (self::type($node) === 'Event') {
                $event = $node;
                break;
            }
        }

        if ($event === null || ! is_string($event['name'] ?? null) || ! is_string($event['startDate'] ?? null)) {
            return null;
        }

        try {
            $startsAt = Carbon::parse($event['startDate'])->utc();
        } catch (\Throwable) {
            return null;
        }

        $endsAt = $startsAt->copy()->addHours(3);

        if (is_string($event['endDate'] ?? null) && $event['endDate'] !== '') {
            try {
                $parsedEnd = Carbon::parse($event['endDate'])->utc();
                if ($parsedEnd->greaterThan($startsAt)) {
                    $endsAt = $parsedEnd;
                }
            } catch (\Throwable) {
                $endsAt = $startsAt->copy()->addHours(3);
            }
        }

        $endsAt = ShowLength::shortenPlaceholder($startsAt, $endsAt);

        $location = is_array($event['location'] ?? null) ? $event['location'] : [];
        $street = self::card($html, 'address');

        if ($street === null && is_array($location['address'] ?? null) && is_string($location['address']['streetAddress'] ?? null)) {
            $street = $location['address']['streetAddress'];
        }

        $venueName = self::card($html, 'venue');

        if ($venueName === null && is_string($location['name'] ?? null)) {
            $venueName = $location['name'];
        }

        $place = self::place($venueName, $street);
        $slug = self::slug($url);

        if ($slug === '') {
            return null;
        }

        $title = trim((string) preg_replace('/\s+/u', ' ', $event['name']));

        if ($title === '') {
            return null;
        }

        return new ParsedEvent(
            slug: $slug,
            title: mb_substr($title, 0, 80),
            startsAt: $startsAt,
            endsAt: $endsAt,
            url: 'https://kupibileti.bg/events/'.$slug.'/',
            category: self::category($nodes),
            description: self::description(is_string($event['description'] ?? null) ? $event['description'] : null),
            imageUrl: self::image($event['image'] ?? null),
            venue: $place['venue'],
            city: $place['city'],
            address: $place['address'],
        );
    }

    /**
     * Dates on a tour page. Each one is its own event page.
     *
     * @return list<string>
     */
    public static function eventLinks(string $html): array
    {
        $urls = [];

        foreach (self::nodes($html) as $node) {
            if (self::type($node) !== 'EventSeries') {
                continue;
            }

            foreach ($node['subEvent'] ?? [] as $sub) {
                if (! is_array($sub)) {
                    continue;
                }

                $url = self::eventUrl(is_string($sub['url'] ?? null) ? $sub['url'] : '');

                if ($url !== null) {
                    $urls[$url] = true;
                }
            }
        }

        preg_match_all('#(?:https://kupibileti\.bg)?(/events/[a-z0-9-]+/?)#i', $html, $found);

        foreach ($found[1] as $path) {
            $url = self::eventUrl('https://kupibileti.bg'.$path);

            if ($url !== null) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    private static function eventUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['kupibileti.bg', 'www.kupibileti.bg'], true)) {
            return null;
        }

        if (preg_match('#^/events/[a-z0-9-]+/?$#i', $path) !== 1) {
            return null;
        }

        return 'https://kupibileti.bg'.rtrim($path, '/').'/';
    }

    /**
     * The hall name is the place. A neighbourhood block number is not.
     *
     * @return array{venue: ?string, city: ?string, address: ?string}
     */
    public static function place(?string $name, ?string $street): array
    {
        $name = NominatimGeocoder::normalize((string) $name);
        $street = NominatimGeocoder::normalize((string) $street);
        $city = null;
        $venue = null;

        if (preg_match('/^(.+?)\s+[-–—]\s+(.+)$/u', $name, $match) === 1) {
            $left = trim($match[1]);
            $right = trim($match[2]);

            if ($right !== '' && ! self::looksLikeVenue($left)) {
                $city = self::titleCity($left);
                $venue = $right;
            }
        }

        if ($venue === null && $name !== '' && self::looksLikeVenue($name)) {
            $venue = $name;
        }

        if ($city === null && $street !== '') {
            $city = self::cityFromStreet($street);
        }

        return [
            'venue' => $venue !== '' ? $venue : null,
            'city' => $city !== '' ? $city : null,
            'address' => self::lookupAddress($street, $city),
        ];
    }

    /**
     * The street or square, then the city, then a landmark. The geocoder treats the first plain part as the city.
     */
    public static function lookupAddress(string $street, ?string $city): ?string
    {
        $street = NominatimGeocoder::normalize($street);

        if ($street === '') {
            return null;
        }

        $streets = [];
        $extras = [];

        foreach (preg_split('/\s*,\s*/u', $street) ?: [] as $part) {
            $part = trim($part);

            if ($part === '' || preg_match('/^\d{4}$/u', $part) === 1) {
                continue;
            }

            if (preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|булевард|пл\.|площад)\s*\S/ui', $part) === 1) {
                $streets[] = $part;

                continue;
            }

            $bare = trim((string) preg_replace('/^(?:гр\.|град|с\.|село)\s+/ui', '', $part));

            if ($city !== null && $bare !== '' && mb_strtolower($bare) === mb_strtolower($city)) {
                continue;
            }

            if ($city === null && preg_match('/^(?:гр\.|град|с\.|село)\s+\S/ui', $part) === 1) {
                $city = self::titleCity($part);

                continue;
            }

            $extras[] = $part;
        }

        if ($streets === [] && $extras === []) {
            return null;
        }

        $chunks = $streets;

        if ($city !== null && $city !== '') {
            $chunks[] = $city;
        }

        foreach ($extras as $extra) {
            $chunks[] = $extra;
        }

        $line = implode(', ', $chunks);

        return $line === '' ? null : $line;
    }

    /**
     * The last info card is the address. The link text is not part of it.
     */
    public static function card(string $html, string $fact): ?string
    {
        if (preg_match('/data-fact="'.preg_quote($fact, '/').'"[\s\S]*?<dd[^>]*>(.*?)<\/dd>/u', $html, $match) !== 1) {
            return null;
        }

        $text = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = trim((string) preg_replace('/\s*Виж на картата.*$/u', '', $text));

        return $text === '' ? null : $text;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function nodes(string $html): array
    {
        preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#si', $html, $matches);
        $nodes = [];

        foreach ($matches[1] as $raw) {
            $decoded = json_decode(html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach (self::flatten($decoded) as $node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private static function flatten(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_values(array_filter($decoded['@graph'], 'is_array'));
        }

        if (array_is_list($decoded)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        return [$decoded];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function type(array $node): string
    {
        $type = $node['@type'] ?? '';

        if (is_array($type)) {
            $type = $type[0] ?? '';
        }

        return is_string($type) ? $type : '';
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function category(array $nodes): string
    {
        foreach ($nodes as $node) {
            if (self::type($node) !== 'BreadcrumbList') {
                continue;
            }

            foreach ($node['itemListElement'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $path = strtolower(trim((string) parse_url((string) ($item['item'] ?? ''), PHP_URL_PATH), '/'));

                if ($path === 'sport') {
                    return 'sport';
                }

                if ($path === 'art') {
                    return 'art';
                }

                if ($path === 'music') {
                    return 'event';
                }
            }
        }

        return 'event';
    }

    private static function description(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $kept = [];

        foreach (preg_split("/\r\n|\n|\r/u", $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                $kept[] = '';

                continue;
            }

            if (preg_match('/kupibileti\.bg продава билети/ui', $line) === 1) {
                continue;
            }

            if (preg_match('/^Билети само на сайта kupibileti\.bg/ui', $line) === 1) {
                continue;
            }

            $kept[] = $line;
        }

        $text = trim((string) preg_replace("/\n{3,}/u", "\n\n", implode("\n", $kept)));

        return $text === '' ? null : $text;
    }

    private static function image(mixed $image): ?string
    {
        if (is_array($image)) {
            $image = $image[0] ?? null;
        }

        if (! is_string($image) || ! str_starts_with($image, 'https://static-client.aws.kupibileti.bg/events/')) {
            return null;
        }

        return $image;
    }

    private static function slug(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = basename($path);
        $slug = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '', $slug));

        return mb_substr($slug, 0, 191);
    }

    private static function looksLikeVenue(string $value): bool
    {
        return preg_match('/зала|стадион|театър|кино|опера|дворец|арена|клуб|ндк|музей|галерия|хотел|парк(?!инг)|комплекс|игрище|цирк/ui', $value) === 1;
    }

    private static function cityFromStreet(string $street): ?string
    {
        if (preg_match('/(?:^|[,\s])(?:гр\.|град)\s+([^,]+)/ui', $street, $match) !== 1) {
            return null;
        }

        $city = self::titleCity(trim($match[1]));

        return $city === '' ? null : $city;
    }

    private static function titleCity(string $city): string
    {
        $city = trim((string) preg_replace('/^(?:гр\.|град|с\.|село)\s+/ui', '', $city));

        return mb_convert_case(mb_strtolower($city), MB_CASE_TITLE, 'UTF-8');
    }
}
