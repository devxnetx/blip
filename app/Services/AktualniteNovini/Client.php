<?php

namespace App\Services\AktualniteNovini;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Client
{
    /**
     * @return list<array<string, mixed>>
     */
    public function events(): array
    {
        $start = now('Europe/Sofia')->startOfDay();
        $until = Setting::importUntil()->timezone('Europe/Sofia')->subDay();

        if ($until->lessThan($start)) {
            $until = $start->copy();
        }

        $events = [];

        for ($page = 1; $page <= 20; $page++) {
            $body = $this->getJson('https://aktualnitenovini.com/wp-json/tribe/events/v1/events', [
                'per_page' => 50,
                'page' => $page,
                'start_date' => $start->toDateString(),
                'end_date' => $until->toDateString(),
                'status' => 'publish',
            ]);
            $batch = $body['events'] ?? null;

            if (! is_array($batch)) {
                throw new RuntimeException('Aktualnite Novini did not return the event list. The import stopped.');
            }

            foreach ($batch as $event) {
                if (is_array($event)) {
                    $events[] = $event;
                }
            }

            $pages = max(1, (int) ($body['total_pages'] ?? 1));

            if ($batch === [] || $page >= $pages) {
                break;
            }
        }

        return $events;
    }

    public function image(string $url): ?string
    {
        if (! self::isImageUrl($url)) {
            throw new RuntimeException('Only an event photo can be fetched.');
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'image/jpeg,image/png,image/webp',
            ])->connectTimeout(10)->timeout(25)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('Aktualnite Novini asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    public static function isImageUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['aktualnitenovini.com', 'www.aktualnitenovini.com'], true)) {
            return false;
        }

        if (str_contains($path, '..')) {
            return false;
        }

        return preg_match('#^/wp-content/uploads/(?:[\p{L}0-9._-]+/)*[\p{L}0-9._%-]+\.(jpe?g|png|webp)$#iu', $path) === 1;
    }

    /**
     * @param  array<string, int|string>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $url, array $query): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['aktualnitenovini.com', 'www.aktualnitenovini.com'], true)) {
            throw new RuntimeException('The importer only reads aktualnitenovini.com.');
        }

        if (! str_starts_with($path, '/wp-json/tribe/events/v1/events')) {
            throw new RuntimeException('The importer only reads the public event list.');
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'application/json',
            ])->connectTimeout(10)->timeout(25)->get($url, $query);
        } catch (ConnectionException) {
            throw new RuntimeException('Aktualnite Novini did not return the event list. The import stopped.');
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('Aktualnite Novini asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Aktualnite Novini did not return the event list. The import stopped.');
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('Aktualnite Novini did not return the event list. The import stopped.');
        }

        return $body;
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.aktualnitenovini_pause_ms', 1500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
