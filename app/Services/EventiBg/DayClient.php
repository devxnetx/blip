<?php

namespace App\Services\EventiBg;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DayClient
{
    public function get(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['eventibg.com', 'www.eventibg.com'], true)) {
            throw new RuntimeException('The importer only reads eventibg.com.');
        }

        if (str_starts_with($path, '/uploads/')) {
            throw new RuntimeException('Event photos are not fetched.');
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'text/html',
            ])->connectTimeout(10)->timeout(25)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('EventiBG asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * @return list<string>
     */
    public function dayUrls(?int $days = null): array
    {
        $count = $days ?? Setting::daysAhead();
        $start = now('Europe/Sofia')->startOfDay();
        $urls = [];

        for ($i = 0; $i < $count; $i++) {
            $date = $start->copy()->addDays($i);
            $urls[] = 'https://eventibg.com/event/'.$date->format('d/m/Y');
        }

        return $urls;
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.eventibg_pause_ms', 1500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
