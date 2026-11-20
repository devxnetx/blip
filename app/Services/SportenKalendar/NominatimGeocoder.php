<?php

namespace App\Services\SportenKalendar;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimGeocoder
{
    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function locate(string $address): ?array
    {
        $query = $this->clean($address);

        if ($query === '') {
            return null;
        }

        $key = 'nominatim:'.md5($query);
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

        $lookup = $query;

        if (! preg_match('/българия|bulgaria/ui', $lookup)) {
            $lookup .= ', България';
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'application/json',
            ])->connectTimeout(10)->timeout(25)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $lookup,
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

        $point = $response->successful() ? self::pointFromPayload($response->json(), $query) : null;
        Cache::put($key, $point ?? 'miss', now()->addDays($point === null ? 3 : 30));

        return $point;
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
        $specific = isset($address['house_number'])
            || isset($address['road'])
            || isset($address['amenity'])
            || isset($address['building'])
            || isset($address['leisure'])
            || isset($address['tourism'])
            || isset($address['historic'])
            || isset($address['man_made']);

        if (! $specific) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function clean(string $address): string
    {
        $address = html_entity_decode($address, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $address = str_replace(['„', '“', '”', '"', '«', '»', '“'], ' ', $address);
        $address = trim((string) preg_replace('/\s+/u', ' ', $address));

        return trim($address, " ,");
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.nominatim_pause_ms', 1100));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
