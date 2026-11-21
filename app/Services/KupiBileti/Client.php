<?php

namespace App\Services\KupiBileti;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Client
{
    public function __construct(private ScrapeLy $scrapeLy) {}

    /**
     * One URL per date. A tour page contributes each of its concerts.
     *
     * @return list<string>
     */
    public function eventUrls(): array
    {
        $body = $this->get('https://kupibileti.bg/sitemap/bg.xml');

        if (! is_string($body) || $body === '') {
            throw new RuntimeException('Kupi Bileti did not return the event list. The import stopped.');
        }

        $urls = [];

        foreach ($this->listed($body, 'events') as $url) {
            $urls[$url] = true;
        }

        foreach ($this->listed($body, 'event-groups') as $group) {
            $html = $this->get($group);

            if (! is_string($html)) {
                continue;
            }

            foreach (EventPage::eventLinks($html) as $url) {
                $urls[$url] = true;
            }
        }

        if ($urls === []) {
            throw new RuntimeException('Kupi Bileti did not return the event list. The import stopped.');
        }

        return array_keys($urls);
    }

    public function get(string $url): ?string
    {
        $this->assertPage($url);
        $this->pause();

        return $this->scrapeLy->html($url);
    }

    public function image(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if ($host !== 'static-client.aws.kupibileti.bg' || ! preg_match('#^/events/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#', $path)) {
            throw new RuntimeException('Only an event photo can be fetched.');
        }

        $this->pause();

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'KostaaBot/1.0 (+https://kostaa.space)',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => 'https://kupibileti.bg/',
            ])->connectTimeout(10)->timeout(25)->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('Kupi Bileti asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    private function assertPage(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (! in_array($host, ['kupibileti.bg', 'www.kupibileti.bg'], true)) {
            throw new RuntimeException('The importer only reads kupibileti.bg.');
        }

        if (str_starts_with($path, '/api/')) {
            throw new RuntimeException('The importer does not call the Kupi Bileti API.');
        }

        if (! preg_match('#^/(?:sitemap/bg\.xml|events/[a-z0-9-]+/?|event-groups/[a-z0-9-]+/?)$#i', $path)) {
            throw new RuntimeException('The importer only reads the event list, event pages, and tour pages.');
        }
    }

    /**
     * @return list<string>
     */
    private function listed(string $body, string $section): array
    {
        preg_match_all('#<loc>\s*(https://kupibileti\.bg/'.$section.'/[a-z0-9-]+/?)\s*</loc>#i', $body, $matches);
        $urls = [];

        foreach ($matches[1] as $url) {
            $urls[rtrim($url, '/').'/'] = true;
        }

        return array_keys($urls);
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.kupibileti_pause_ms', 1500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
