<?php

namespace App\Services\Discord;

use App\Models\ChangelogEntry;
use App\Models\Pin;
use App\Models\Setting;
use App\Support\ChangelogMarkdown;
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

        $content = '**Току-що в '.$city['name'].':** '.$this->masked($this->plainTitle($pin), $this->pinUrl($pin));

        return $this->post($channel, $content);
    }

    /**
     * Post or update the changelog channel. A forum channel gets one thread per entry.
     */
    public function syncChangelog(ChangelogEntry $entry): bool
    {
        if (! $this->enabled() || ! $entry->isPublished()) {
            return false;
        }

        $channel = $this->changelogChannel();

        if ($channel === null) {
            Log::warning('Discord changelog channel is missing.');

            return false;
        }

        $content = ChangelogMarkdown::message($entry->title, $entry->body, $this->changelogUrl($entry));

        if ($entry->discord_channel_id !== null && $entry->discord_message_id !== null) {
            $updated = $this->request('PATCH', '/channels/'.$entry->discord_channel_id.'/messages/'.$entry->discord_message_id, [
                'content' => $content,
            ]);

            if (is_array($updated) && isset($updated['id'])) {
                if ($entry->discord_is_thread) {
                    $this->request('PATCH', '/channels/'.$entry->discord_channel_id, [
                        'name' => $this->threadName($entry->title),
                    ]);
                }

                return true;
            }
        }

        if ($channel['type'] === 15) {
            $created = $this->request('POST', '/channels/'.$channel['id'].'/threads', [
                'name' => $this->threadName($entry->title),
                'auto_archive_duration' => 10080,
                'message' => ['content' => $content],
            ]);

            if (! is_array($created) || ! isset($created['id'])) {
                return false;
            }

            $entry->discord_channel_id = (string) $created['id'];
            $entry->discord_message_id = (string) ($created['message']['id'] ?? $created['id']);
            $entry->discord_is_thread = true;
            $entry->save();

            return true;
        }

        $created = $this->request('POST', '/channels/'.$channel['id'].'/messages', [
            'content' => $content,
        ]);

        if (! is_array($created) || ! isset($created['id'])) {
            return false;
        }

        $entry->discord_channel_id = $channel['id'];
        $entry->discord_message_id = (string) $created['id'];
        $entry->discord_is_thread = false;
        $entry->save();

        return true;
    }

    public function removeChangelog(ChangelogEntry $entry): void
    {
        if ($this->enabled() && $entry->discord_channel_id !== null) {
            if ($entry->discord_is_thread) {
                $this->request('DELETE', '/channels/'.$entry->discord_channel_id);
            } elseif ($entry->discord_message_id !== null) {
                $this->request('DELETE', '/channels/'.$entry->discord_channel_id.'/messages/'.$entry->discord_message_id);
            }
        }

        $entry->discord_channel_id = null;
        $entry->discord_message_id = null;
        $entry->discord_is_thread = false;
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
            ->map(fn (Pin $pin): string => $this->eventLine($pin))
            ->values()
            ->all();

        $heading = '**Събития днес в** '.$this->masked($city['name'], $this->todayUrl($city['slug']));

        if ($lines === []) {
            $lines = ['Няма събития за днес.'];
        }

        $sent = true;

        foreach ($this->messages($heading, $lines) as $content) {
            $sent = $this->post($channel, $content) && $sent;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, Pin>  $running
     * @param  Collection<int, Pin>  $upcoming
     */
    public function postLive(array $city, Collection $running, Collection $upcoming, ?int $aheadHours = null): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $channel = $this->channelId($city['name']);

        if ($channel === null) {
            Log::warning('Discord city channel is missing.', ['city' => $city['name']]);

            return false;
        }

        $ahead = $aheadHours ?? Setting::discordAheadHours();
        $heading = '**В момента в** '.$this->masked($city['name'], $this->cityUrl($city['slug']));
        $lines = $this->sectionLines($running);
        $lines[] = '';
        $lines[] = '**Предстоящи до '.$this->hourPhrase($ahead).'**';
        array_push($lines, ...$this->sectionLines($upcoming));

        $sent = true;

        foreach ($this->messages($heading, $lines) as $content) {
            $sent = $this->post($channel, $content) && $sent;
        }

        return $sent;
    }

    public function cityUrl(string $slug): string
    {
        return $this->root().'/map?'.http_build_query([
            'city' => $slug,
        ]);
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

    private function eventLine(Pin $pin): string
    {
        return $this->plainTitle($pin).' · '.$this->masked('повече информация', $this->pinUrl($pin));
    }

    /**
     * @param  Collection<int, Pin>  $pins
     * @return list<string>
     */
    private function sectionLines(Collection $pins): array
    {
        if ($pins->isEmpty()) {
            return ['Няма събития.'];
        }

        return $pins->map(fn (Pin $pin): string => $this->plainTitle($pin).' · '.PinPage::whenLabel($pin).' · '.$this->masked('повече информация', $this->pinUrl($pin)))->values()->all();
    }

    private function hourPhrase(int $hours): string
    {
        return $hours === 1 ? '1 час' : $hours.' часа';
    }

    /**
     * @return array<string, array{city: array{name: string, slug: string, latitude: float, longitude: float, find: string}, pins: Collection<int, Pin>}>
     */
    public function todayByCity(?string $slug = null): array
    {
        [$cities, $wanted] = $this->selectedCities($slug);

        if ($slug !== null && $slug !== '' && $wanted === null) {
            return [];
        }

        $pins = Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->occursToday()
            ->get();

        return $this->rowsFor($cities, $wanted, $pins);
    }

    /**
     * Events that already started, and events that start within the ahead window.
     *
     * @return array<string, array{city: array{name: string, slug: string, latitude: float, longitude: float, find: string}, running: Collection<int, Pin>, upcoming: Collection<int, Pin>}>
     */
    public function liveByCity(?string $slug = null, ?int $aheadHours = null): array
    {
        [$cities, $wanted] = $this->selectedCities($slug);

        if ($slug !== null && $slug !== '' && $wanted === null) {
            return [];
        }

        $ahead = $aheadHours ?? Setting::discordAheadHours();
        $pins = Pin::query()
            ->onMap()
            ->whereNull('suppressed_at')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now()->addHours($ahead))
            ->get();

        $rows = [];

        foreach ($this->rowsFor($cities, $wanted, $pins) as $key => $row) {
            $running = $row['pins']
                ->filter(fn (Pin $pin): bool => $pin->starts_at !== null && $pin->starts_at->lte(now()))
                ->sortBy(fn (Pin $pin) => $pin->starts_at?->getTimestamp() ?? 0)
                ->values();
            $upcoming = $row['pins']
                ->filter(fn (Pin $pin): bool => $pin->starts_at !== null && $pin->starts_at->gt(now()))
                ->sortBy(fn (Pin $pin) => $pin->starts_at?->getTimestamp() ?? 0)
                ->values();

            $rows[$key] = [
                'city' => $row['city'],
                'running' => $running,
                'upcoming' => $upcoming,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, slug: string, latitude: float, longitude: float, find: string}>  $cities
     * @param  array{name: string, slug: string, latitude: float, longitude: float, find: string}|null  $wanted
     * @param  Collection<int, Pin>  $pins
     * @return array<string, array{city: array{name: string, slug: string, latitude: float, longitude: float, find: string}, pins: Collection<int, Pin>}>
     */
    private function rowsFor(array $cities, ?array $wanted, Collection $pins): array
    {
        $buckets = [];

        foreach ($cities as $city) {
            if ($wanted !== null && $city['slug'] !== $wanted['slug']) {
                continue;
            }

            $buckets[$city['slug']] = [];
        }

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

    /**
     * @return array{0: list<array{name: string, slug: string, latitude: float, longitude: float, find: string}>, 1: array{name: string, slug: string, latitude: float, longitude: float, find: string}|null}
     */
    private function selectedCities(?string $slug): array
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
        }

        return [$cities, $wanted];
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
     * @return array{id: string, type: int}|null
     */
    private function changelogChannel(): ?array
    {
        foreach ($this->channels() as $channel) {
            $type = $channel['type'] ?? null;

            if ($type !== 0 && $type !== 15) {
                continue;
            }

            $name = mb_strtolower((string) ($channel['name'] ?? ''), 'UTF-8');

            if ($name === 'changelog' || $name === 'chanelog') {
                return [
                    'id' => (string) $channel['id'],
                    'type' => (int) $type,
                ];
            }
        }

        return null;
    }

    private function changelogUrl(ChangelogEntry $entry): string
    {
        return $this->root().'/changelog#'.$entry->id;
    }

    private function threadName(string $title): string
    {
        $name = trim(str_replace(["\r", "\n"], ' ', $title));

        if ($name === '') {
            $name = 'Промяна';
        }

        return mb_substr($name, 0, 100);
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

                $url = 'https://discord.com/api/v10'.$path;
                $response = match ($method) {
                    'GET' => $pending->get($url),
                    'PATCH' => $pending->patch($url, $body ?? []),
                    'DELETE' => $pending->delete($url),
                    default => $pending->post($url, $body ?? []),
                };
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

            if ($method === 'DELETE') {
                return ['deleted' => true];
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
