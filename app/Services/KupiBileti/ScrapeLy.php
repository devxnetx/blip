<?php

namespace App\Services\KupiBileti;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ScrapeLy
{
    /**
     * The public page, as HTML. Images stay on the photo CDN.
     */
    public function html(string $url): ?string
    {
        $token = config('services.scrapely.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Scrape.ly is not configured.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(90)
                ->get('https://api.scrape.ly/v2/querystone/web-scraper', [
                    'url' => $url,
                    'format' => 'json',
                    'country' => 'BG',
                    'timeout' => 70,
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (in_array($response->status(), [429, 503], true)) {
            throw new RuntimeException('Scrape.ly asked us to slow down. The import stopped.');
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Scrape.ly rejected the API token. The import stopped.');
        }

        if ($response->status() === 402) {
            throw new RuntimeException('Scrape.ly has no credit left. The import stopped.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        $pageStatus = $payload['status'] ?? null;

        if (is_numeric($pageStatus) && in_array((int) $pageStatus, [429, 503], true)) {
            throw new RuntimeException('Kupi Bileti asked us to slow down. The import stopped.');
        }

        if (isset($payload['error']) || (is_numeric($pageStatus) && (int) $pageStatus >= 400)) {
            return null;
        }

        $html = $payload['html'] ?? null;

        return is_string($html) && $html !== '' ? $html : null;
    }
}
