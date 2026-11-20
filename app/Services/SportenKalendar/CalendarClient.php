<?php

namespace App\Services\SportenKalendar;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CalendarClient
{
    private const BLOCKED = [
        '/tarsi',
        '/alleventsdata',
        '/monthsevents',
        '/alluserseventsdata',
        '/sportevents',
        '/customer/events/mbd',
    ];

    public function get(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['sportenkalendar.bg', 'www.sportenkalendar.bg'], true)) {
            throw new RuntimeException('The importer only reads sportenkalendar.bg.');
        }

        foreach (self::BLOCKED as $blocked) {
            if (str_starts_with($path, $blocked)) {
                throw new RuntimeException('That calendar feed is not fetched.');
            }
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml,image/*;q=0.8',
            ])->connectTimeout(10)->timeout(25)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('The sports calendar asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    /**
     * @param  (callable(string): void)|null  $progress
     * @return list<string>
     */
    public function eventUrls(?callable $progress = null): array
    {
        $events = [];
        $sitemap = $this->get('https://sportenkalendar.bg/sitemap') ?? '';

        preg_match_all('#https?://(?:www\.)?sportenkalendar\.bg/sybitie/([a-z0-9\-]+)#', $sitemap, $listed);

        foreach ($listed[1] as $slug) {
            $events[$slug] = 'https://sportenkalendar.bg/sybitie/'.$slug;
        }

        if ($progress !== null) {
            $progress('sitemap lists '.count($events).' events');
        }

        preg_match_all('#https?://(?:www\.)?sportenkalendar\.bg/sportove/([a-z0-9\-]+)(?![a-z0-9\-/])#', $sitemap, $sports);
        $slugs = array_values(array_unique($sports[1]));
        $total = count($slugs);
        $read = 0;

        foreach ($slugs as $slug) {
            $read++;
            $html = $this->get('https://sportenkalendar.bg/sportove/'.$slug);

            if ($progress !== null && ($read === 1 || $read % 20 === 0 || $read === $total)) {
                $progress('read '.$read.'/'.$total.' sports, '.count($events).' events');
            }

            if (! is_string($html)) {
                continue;
            }

            preg_match_all('#href="(/sybitie/[a-z0-9\-]+)"#', $html, $found);

            foreach ($found[1] as $path) {
                $events[basename($path)] = 'https://sportenkalendar.bg'.$path;
            }
        }

        return array_values($events);
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.sporten_pause_ms', 1500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
