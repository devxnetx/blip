<?php

namespace App\Services\SportenKalendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimGeocoder
{
    private bool $answered = false;

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function locate(?string $address, ?string $venue = null): ?array
    {
        $attempts = self::lookupAttempts($address, $venue);

        if ($attempts === []) {
            return null;
        }

        $key = 'nominatim:v3:'.md5((string) json_encode($attempts, JSON_UNESCAPED_UNICODE));
        $cached = Cache::get($key);

        if ($cached === 'miss') {
            return null;
        }

        if (is_array($cached) && isset($cached['latitude'], $cached['longitude'])) {
            return [
                'latitude' => (float) $cached['latitude'],
                'longitude' => (float) $cached['longitude'],
            ];
        }

        $answered = false;
        $point = null;

        foreach ($attempts as $params) {
            $point = $this->search($params);

            if ($this->answered) {
                $answered = true;
            }

            if ($point !== null) {
                break;
            }
        }

        if ($answered) {
            Cache::put($key, $point ?? 'miss', now()->addDays($point === null ? 3 : 30));
        }

        return $point;
    }

    /**
     * Street first, then the hall name, then a named park. A city by itself stays one free-text query.
     *
     * @return list<array<string, string>>
     */
    public static function lookupAttempts(?string $address, ?string $venue = null): array
    {
        $address = self::normalize((string) $address);
        $venue = self::normalize((string) $venue);
        $attempts = [];
        $seen = [];
        $structured = $address !== '' ? self::structuredQuery($address) : null;

        if ($structured !== null) {
            $attempts[] = $structured;
        }

        $city = $structured['city'] ?? self::cityName($address);
        $places = $venue !== '' ? [$venue] : [];

        foreach (self::placeNames($address) as $place) {
            $places[] = $place;
        }

        foreach ($places as $place) {
            $query = self::placeQuery($place, $city);

            if ($query === null || isset($seen[$query])) {
                continue;
            }

            $seen[$query] = true;
            $attempts[] = [
                'q' => $query,
                '_city' => $city ?? '',
            ];
        }

        if ($places === [] && $structured === null && $address !== '') {
            $query = self::placeQuery($address, null);

            if ($query !== null) {
                $attempts[] = ['q' => $query, '_city' => ''];
            }
        }

        return $attempts;
    }

    /**
     * @param  array<string, string>  $params
     * @return array{latitude: float, longitude: float}|null
     */
    private function search(array $params): ?array
    {
        $this->answered = false;
        $expectedCity = $params['_city'] ?? null;
        unset($params['_city']);
        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'application/json',
            ])->connectTimeout(10)->timeout(25)->get('https://nominatim.openstreetmap.org/search', $params + [
                'format' => 'jsonv2',
                'limit' => 1,
                'countrycodes' => 'bg',
                'addressdetails' => 1,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('The address lookup asked us to slow down. The import stopped.');
        }

        $this->answered = true;

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $city = is_string($expectedCity) && $expectedCity !== '' ? $expectedCity : ($params['city'] ?? null);

        if (! self::cityAgrees($payload, is_string($city) ? $city : null)) {
            return null;
        }

        return self::pointFromPayload($payload, '');
    }

    /**
     * A street plus a city. Nominatim misses the full sidebar sentence.
     *
     * @return array{street: string, city: string}|null
     */
    public static function structuredQuery(string $address): ?array
    {
        $parts = preg_split('/\s*,\s*/u', $address) ?: [];
        $street = null;
        $number = null;
        $cities = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|булевард|пл\.|площад)\s*(.+)/ui', $part, $match)) {
                $rest = trim($match[1], " \t\"'«».");

                if (preg_match('/№\s*(\d+[а-яa-z]?)/ui', $rest, $numberMatch)) {
                    $number = $numberMatch[1];
                    $rest = trim((string) preg_replace('/№\s*\d+[а-яa-z]?/ui', '', $rest));
                } elseif (preg_match('/^(.*?)(?:\s+)(\d+[а-яa-z]?)$/u', $rest, $numberMatch)) {
                    $rest = trim($numberMatch[1]);
                    $number = $numberMatch[2];
                }

                $rest = trim($rest, " \t\"'«».");

                if ($rest !== '') {
                    $street = $rest;
                }

                continue;
            }

            if (preg_match('/зала|стадион|комплекс|хотел|планина|поляна|парк(?!инг)|арена|басейн|игрище|дворец/ui', $part)) {
                continue;
            }

            $cities[] = $part;
        }

        if ($street === null || $cities === []) {
            return null;
        }

        $line = $street.($number !== null ? ' '.$number : '');

        return [
            'street' => $line,
            'city' => $cities[0],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function pointFromPayload(mixed $payload, string $query): ?array
    {
        if (! is_array($payload) || ! isset($payload[0]) || ! is_array($payload[0])) {
            return null;
        }

        $row = $payload[0];
        if (! isset($row['lat'], $row['lon']) || ! is_numeric($row['lat']) || ! is_numeric($row['lon'])) {
            return null;
        }

        $latitude = (float) $row['lat'];
        $longitude = (float) $row['lon'];

        if ($latitude < 41.0 || $latitude > 44.3 || $longitude < 22.0 || $longitude > 28.7) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $category = (string) ($row['category'] ?? '');
        $type = (string) ($row['type'] ?? '');
        $specific = isset($address['house_number'])
            || isset($address['road'])
            || isset($address['amenity'])
            || isset($address['building'])
            || isset($address['leisure'])
            || isset($address['tourism'])
            || isset($address['historic'])
            || isset($address['man_made'])
            || isset($address['park'])
            || ($category === 'leisure' && in_array($type, ['park', 'sports_centre', 'stadium', 'pitch', 'swimming_pool', 'track', 'sports_hall'], true));

        if (! $specific) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    public static function normalize(string $address): string
    {
        $address = html_entity_decode($address, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $address = str_replace(['„', '“', '”', '"', '«', '»'], ' ', $address);
        $address = trim((string) preg_replace('/\s+/u', ' ', $address));

        return trim($address, " ,");
    }

    private static function cityName(string $address): ?string
    {
        foreach (self::chunks($address) as $part) {
            if (self::isStreet($part) || self::isPlace($part)) {
                continue;
            }

            $part = trim((string) preg_replace('/^(?:гр\.|град|с\.|село)\s+/ui', '', $part), " .");

            if ($part !== '') {
                return $part;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function placeNames(string $address): array
    {
        $names = [];

        foreach (self::chunks($address) as $part) {
            if (self::isPlace($part)) {
                $names[] = $part;
            }
        }

        return $names;
    }

    private static function placeQuery(string $place, ?string $city): ?string
    {
        $place = self::normalize($place);

        if ($place === '') {
            return null;
        }

        if ($city !== null && $city !== '' && ! preg_match('/'.preg_quote($city, '/').'/ui', $place)) {
            $place .= ', '.$city;
        }

        if (! preg_match('/българия|bulgaria/ui', $place)) {
            $place .= ', България';
        }

        return $place;
    }

    /**
     * @return list<string>
     */
    private static function chunks(string $address): array
    {
        $parts = preg_split('/\s*,\s*/u', $address) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            $parts,
        ), fn (string $part): bool => $part !== ''));
    }

    private static function isStreet(string $part): bool
    {
        return preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|булевард|пл\.|площад)\s*\S/ui', $part) === 1;
    }

    private static function isPlace(string $part): bool
    {
        return preg_match('/зала|стадион|комплекс|хотел|парк(?!инг)|арена|басейн|игрище|дворец/ui', $part) === 1;
    }

    public static function cityAgrees(mixed $payload, ?string $city): bool
    {
        if ($city === null || $city === '') {
            return true;
        }

        if (! is_array($payload) || ! isset($payload[0]) || ! is_array($payload[0])) {
            return true;
        }

        $address = is_array($payload[0]['address'] ?? null) ? $payload[0]['address'] : [];
        $found = null;

        foreach (['city', 'town', 'village', 'municipality'] as $key) {
            if (isset($address[$key]) && is_string($address[$key]) && $address[$key] !== '') {
                $found = $address[$key];
                break;
            }
        }

        if ($found === null) {
            return true;
        }

        $found = mb_strtolower($found);
        $city = mb_strtolower($city);

        return $found === $city || str_contains($found, $city) || str_contains($city, $found);
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.nominatim_pause_ms', 1100));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
