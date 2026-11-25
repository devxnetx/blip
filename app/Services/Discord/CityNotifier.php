<?php

namespace App\Services\Discord;

use App\Models\Pin;
use App\Support\PinPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CityNotifier
{
    /** @var list<array<string, mixed>>|null */
    private ?array $channelCache = null;

    public function enabled(): bool
    {
        return $this->token() !== '' && $this->guildId() !== '';
    }

    /**
     * A pin from a phone or browser. Imported events stay out of this message.
     */
    public function announcePin(Pin $pin): bool
    {
        if (! $this->enabled() || $pin->isImported() || $pin->latitude === null || $pin->longitude === null) {
            return false;
        }

        $city = PinPage::cityFor($pin, PinPage::directory());

        if ($city === null) {
            return false;
        }

        $channel = $this->channelId($city['name']);

        if ($channel === null) {
            Log::warning('Discord city channel is missing.', ['city' => $city['name']]);

            return false;
        }

        $when = ($pin->starts_at ?? $pin->created_at)?->timezone('Europe/Sofia')->format('d.m, H:i') ?? '';
        $url = $this->root().'/map?pin='.$pin->id;
        $content = "New pin was created: {$pin->message}\n{$when}\n{$url}";

        return $this->post($channel, $content);
    }

    /**
     * @param  Collection<int, Pin>  $pins
     */
    public function postToday(array $city, Collection $pins): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $channel = $this->channelId($city['name']);

        if ($channel === null) {
            Log::warning('Discord city channel is missing.', ['city' => $city['name']]);

            return false;
        }

        $lines = $pins
            ->sortBy(fn (Pin $pin) => ($pin->starts_at ?? $pin->created_at)?->getTimestamp() ?? 0)
            ->map(fn (Pin $pin): string => $this->plainTitle($pin).' '.$this->masked('more info', $this->pinUrl($pin)))
            ->values()
            ->all();

        $heading = $this->masked('Today in '.$city['name'].':', $this->todayUrl($city['slug']));

        if ($lines === []) {
            $lines = ['Няма събития за днес.'];
        }

        $sent = true;

        foreach ($this->messages($heading, $lines) as $content) {
            $sent = $this->post($channel, $content) && $sent;
        }

        return $sent;
    }

    public static function channelName(string $cityName): string
    {
        return str_replace(' ', '-', mb_strtolower($cityName, 'UTF-8'));
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public function messages(string $heading, array $lines): array
    {
        $messages = [];
        $current = $heading;

        foreach ($lines as $line) {
            $next = $current."\n".$line;

            if (mb_strlen($next) > 1900 && $current !== $heading && $current !== '') {
                $messages[] = $current;
                $current = $line;
                continue;
            }

            $current = $next;
        }

        $messages[] = $current;

        return $messages;
    }

    public function masked(string $label, string $url): string
    {
        return '['.$label.'](<'.$url.'>)';
    }

    public function todayUrl(string $slug): string
    {
        return $this->root().'/map?'.http_build_query([
            'city' => $slug,
            'period' => 'today',
        ]);
    }

    public function pinUrl(Pin $pin): string
    {
        return $this->root().'/map?pin='.$pin->id;
    }

    private function plainTitle(Pin $pin): string
    {
        $title = trim(str_replace(["\r", "\n", '[', ']'], ' ', $pin->message));

        return $title !== '' ? $title : 'Пин';
    }

    /**
     * @return array<string, array{city: array{name: string, slug: string, latitude: float, longitude: float, find: string}, pins: Collection<int, Pin>}>
     */
    public function todayByCity(?string $slug = null): array
    {
        $cities = PinPage::directory();
        $wanted = null;

        if ($slug !== null && $slug !== '') {
            $wanted = PinPage::cityBySlug($cities, $slug);

            if ($wanted === null) {
                foreach ($cities as $city) {
                    if (self::channelName($city['name']) === self::channelName($slug) || $city['name'] === $slug) {
                        $wanted = $city;
                        break;
                    }
                }
            }

            if ($wanted === null) {
                return [];
            }
        }

        $buckets = [];

        foreach ($cities as $city) {
            if ($wanted !== null && $city['slug'] !== $wanted['slug']) {
                continue;
            }

            $buckets[$city['slug']] = [];
        }

        $pins = Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->occursToday()
            ->get();

        foreach ($pins as $pin) {
            $city = PinPage::cityFor($pin, $cities);

            if ($city === null || ! array_key_exists($city['slug'], $buckets)) {
                continue;
            }

            $buckets[$city['slug']][] = $pin;
        }

        $rows = [];

        foreach ($cities as $city) {
            if (! array_key_exists($city['slug'], $buckets)) {
                continue;
            }

            $rows[$city['slug']] = [
                'city' => $city,
                'pins' => collect($buckets[$city['slug']]),
            ];
        }

        return $rows;
    }

    private function channelId(string $cityName): ?string
    {
        $want = self::channelName($cityName);

        foreach ($this->channels() as $channel) {
            if (($channel['type'] ?? null) !== 0) {
                continue;
            }

            $name = self::channelName((string) ($channel['name'] ?? ''));

            if ($name === $want) {
                return (string) $channel['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function channels(): array
    {
        if ($this->channelCache !== null) {
            return $this->channelCache;
        }

        $response = $this->request('GET', '/guilds/'.$this->guildId().'/channels');

        if ($response === null || ! is_array($response) || ! array_is_list($response)) {
            return [];
        }

        $this->channelCache = $response;

        return $this->channelCache;
    }

    private function post(string $channelId, string $content): bool
    {
        $response = $this->request('POST', '/channels/'.$channelId.'/messages', [
            'content' => $content,
        ]);

        return is_array($response) && isset($response['id']);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function request(string $method, string $path, ?array $body = null): ?array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $pending = Http::withHeaders([
                    'Authorization' => 'Bot '.$this->token(),
                    'User-Agent' => 'KostaaBot/1.0 (https://kostaa.space)',
                ])->timeout(12)->acceptJson();

                $response = $method === 'GET'
                    ? $pending->get('https://discord.com/api/v10'.$path)
                    : $pending->post('https://discord.com/api/v10'.$path, $body ?? []);
            } catch (\Throwable $exception) {
                Log::warning('Discord request failed.', ['path' => $path, 'error' => $exception->getMessage()]);

                return null;
            }

            if ($response->status() === 429 && $attempt < 2) {
                usleep((int) (((float) $response->json('retry_after', 1) + 0.2) * 1000000));
                continue;
            }

            if (! $response->successful()) {
                Log::warning('Discord request was refused.', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();

            return is_array($json) ? $json : null;
        }

        return null;
    }

    private function root(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function token(): string
    {
        return trim((string) config('blip.discord_bot_token'));
    }

    private function guildId(): string
    {
        return trim((string) config('blip.discord_guild_id'));
    }
}
