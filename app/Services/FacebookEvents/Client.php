<?php

namespace App\Services\FacebookEvents;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Client
{
    private const ACTOR = 'https://api.apify.com/v2/acts/unseenuser~fb-events/run-sync-get-dataset-items';

    /**
     * @param  array{name: string, url: string, query: string, city_id: string, latitude: float, longitude: float, match: list<string>}  $city
     * @return list<array<string, mixed>>
     */
    public function events(array $city, int $limit): array
    {
        $this->assertCity($city);

        if ($limit < 1) {
            return [];
        }

        $rows = $this->run([
            'cityUrls' => [$city['url']],
            'cityTime' => '',
            'onlyUpcoming' => true,
            'maxResults' => $limit,
        ]);

        if ($rows !== []) {
            return $rows;
        }

        return $this->run([
            'queries' => [$city['query']],
            'onlyUpcoming' => true,
            'maxResults' => $limit,
        ]);
    }

    /**
     * City page and keyword search together, with the actor's own page cap off.
     *
     * @param  array{name: string, url: string, query: string, city_id: string}  $city
     * @param  list<string>  $queries
     * @return list<array<string, mixed>>
     */
    public function wide(array $city, array $queries, int $limit = 0, ?callable $progress = null): array
    {
        $this->assertCity($city);

        $started = $this->postJson('https://api.apify.com/v2/acts/unseenuser~fb-events/runs?timeout=3600', [
            'cityUrls' => [$city['url']],
            'queries' => array_values($queries),
            'cityTime' => '',
            'onlyUpcoming' => false,
            'maxResults' => $limit,
        ]);
        $runId = (string) ($started['data']['id'] ?? '');

        if (preg_match('/^[A-Za-z0-9]{10,32}$/', $runId) !== 1) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $rows = $this->collectRun($runId, $progress);
        $unique = [];

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $key = is_int($id) || (is_string($id) && $id !== '') ? (string) $id : 'row-'.count($unique);

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    /**
     * Wait for an actor run that is already going, then return every dataset row.
     *
     * @param  (callable(string): void)|null  $progress
     * @return list<array<string, mixed>>
     */
    public function collectRun(string $runId, ?callable $progress = null): array
    {
        if (preg_match('/^[A-Za-z0-9]{10,32}$/', $runId) !== 1) {
            throw new RuntimeException('The Facebook run id is not valid. The import stopped.');
        }

        $deadline = time() + 20 * 60;
        $lastCount = null;

        while (true) {
            $run = $this->getJson('https://api.apify.com/v2/actor-runs/'.$runId);
            $data = is_array($run['data'] ?? null) ? $run['data'] : [];
            $status = (string) ($data['status'] ?? '');
            $datasetId = (string) ($data['defaultDatasetId'] ?? '');
            $count = 0;

            if ($datasetId !== '') {
                $meta = $this->getJson('https://api.apify.com/v2/datasets/'.$datasetId);
                $count = (int) ($meta['data']['itemCount'] ?? 0);
            }

            if ($progress !== null && $count !== $lastCount) {
                $progress(sprintf('ACTOR %s items=%d', $status !== '' ? $status : 'UNKNOWN', $count));
                $lastCount = $count;
            }

            if (in_array($status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)) {
                if (preg_match('/^[A-Za-z0-9]{10,32}$/', $datasetId) !== 1) {
                    throw new RuntimeException('Facebook events did not return a list. The import stopped.');
                }

                return $this->datasetItems($datasetId);
            }

            if ($status === '' || time() > $deadline) {
                throw new RuntimeException('The Facebook run did not finish. The import stopped.');
            }

            sleep(15);
        }
    }

    public function image(string $url): ?string
    {
        if (! self::isCoverUrl($url)) {
            return null;
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
            throw new RuntimeException('Facebook asked us to slow down. The import stopped.');
        }

        if (! $response->successful()) {
            return null;
        }

        return $response->body();
    }

    public static function isCoverUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme !== 'https' || $host === '' || str_contains($path, '..')) {
            return false;
        }

        return $host === 'fbcdn.net' || str_ends_with($host, '.fbcdn.net');
    }

    /**
     * @param  array{name: string, url: string, query: string, city_id: string}  $city
     */
    private function assertCity(array $city): void
    {
        $known = config('blip.facebook_cities', []);
        $match = false;

        foreach (is_array($known) ? $known : [] as $row) {
            if (is_array($row) && ($row['url'] ?? null) === $city['url'] && ($row['query'] ?? null) === $city['query']) {
                $match = true;
                break;
            }
        }

        if (! $match) {
            throw new RuntimeException('The importer only reads the configured cities.');
        }

        $host = strtolower((string) parse_url($city['url'], PHP_URL_HOST));
        $path = (string) (parse_url($city['url'], PHP_URL_PATH) ?? '');

        if (! in_array($host, ['facebook.com', 'www.facebook.com'], true)) {
            throw new RuntimeException('The importer only reads the configured cities.');
        }

        if (preg_match('#^/events/explore/[a-z0-9-]+/\d+/?$#', $path) !== 1) {
            throw new RuntimeException('The importer only reads the configured cities.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function run(array $input, int $timeout = 300): array
    {
        $token = config('services.apify.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The Apify token is missing. The import stopped.');
        }

        $this->pause();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout($timeout + 30)
                ->post(self::ACTOR.'?timeout='.$timeout, $input);
        } catch (ConnectionException) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $body = $response->json();

        if (! is_array($body) || ! array_is_list($body)) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $rows = [];

        foreach ($body as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $token = config('services.apify.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The Apify token is missing. The import stopped.');
        }

        if (! str_starts_with($url, 'https://api.apify.com/v2/')) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(15)
                ->timeout(60)
                ->get($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $input): array
    {
        $token = config('services.apify.token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The Apify token is missing. The import stopped.');
        }

        if (! str_starts_with($url, 'https://api.apify.com/v2/acts/unseenuser~fb-events/runs')) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $this->pause();

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(15)
                ->timeout(60)
                ->post($url, $input);
        } catch (ConnectionException) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Facebook events did not return a list. The import stopped.');
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function datasetItems(string $datasetId): array
    {
        $rows = [];
        $offset = 0;

        while (true) {
            $page = $this->getJson('https://api.apify.com/v2/datasets/'.$datasetId.'/items?clean=1&offset='.$offset.'&limit=1000');
            $list = array_is_list($page) ? $page : ($page['items'] ?? null);

            if (! is_array($list) || ! array_is_list($list) || $list === []) {
                break;
            }

            foreach ($list as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            if (count($list) < 1000) {
                break;
            }

            $offset += count($list);
        }

        return $rows;
    }

    private function pause(): void
    {
        $ms = max(0, (int) config('blip.facebook_pause_ms', 1500));

        if ($ms > 0) {
            usleep($ms * 1000);
        }
    }
}
