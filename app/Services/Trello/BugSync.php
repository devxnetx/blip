<?php

namespace App\Services\Trello;

use App\Models\TrelloBugCard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BugSync
{
    /** @var array<string, string> */
    private const TAG_COLORS = [
        'сайт' => 'blue',
        'приложение' => 'orange',
    ];

    public function enabled(): bool
    {
        return $this->discordToken() !== ''
            && $this->guildId() !== ''
            && $this->key() !== ''
            && $this->token() !== '';
    }

    /**
     * @return array{created: int, updated: int, failed: int}
     */
    public function sync(): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'failed' => 0];

        if (! $this->enabled()) {
            return $counts;
        }

        $channel = $this->discord('/channels/'.$this->bugChannelId());
        $tags = $this->tagNames(is_array($channel) ? $channel : []);
        $listId = $this->listId();

        if ($listId === null) {
            Log::warning('Trello bug list is missing.', ['list' => $this->listName()]);

            return ['created' => 0, 'updated' => 0, 'failed' => 1];
        }

        foreach ($this->threads() as $thread) {
            $threadId = (string) ($thread['id'] ?? '');
            $title = trim(str_replace(["\r", "\n"], ' ', (string) ($thread['name'] ?? '')));

            if ($threadId === '' || $title === '' || (string) ($thread['parent_id'] ?? '') !== $this->bugChannelId()) {
                continue;
            }

            $names = $this->appliedNames($thread, $tags);
            $card = TrelloBugCard::query()->where('discord_thread_id', $threadId)->first();

            if ($card !== null) {
                if ($this->sameTags($card->tags ?? [], $names)) {
                    continue;
                }

                $ok = $this->updateLabels($card->trello_card_id, $names);

                if ($ok) {
                    $card->tags = $names;
                    $card->save();
                    $counts['updated']++;
                } else {
                    $counts['failed']++;
                }

                continue;
            }

            $created = $this->createCard($listId, $title, $this->description($threadId), $names);

            if ($created === null) {
                $counts['failed']++;

                continue;
            }

            TrelloBugCard::query()->create([
                'discord_thread_id' => $threadId,
                'trello_card_id' => $created,
                'tags' => $names,
            ]);
            $counts['created']++;
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $channel
     * @return array<string, string>
     */
    private function tagNames(array $channel): array
    {
        $names = [];

        foreach ($channel['available_tags'] ?? [] as $tag) {
            if (is_array($tag) && isset($tag['id'], $tag['name'])) {
                $names[(string) $tag['id']] = (string) $tag['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $thread
     * @param  array<string, string>  $tags
     * @return list<string>
     */
    private function appliedNames(array $thread, array $tags): array
    {
        $names = [];

        foreach ($thread['applied_tags'] ?? [] as $id) {
            $name = $tags[(string) $id] ?? null;

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @param  list<string>  $stored
     * @param  list<string>  $names
     */
    private function sameTags(array $stored, array $names): bool
    {
        $stored = array_values($stored);
        sort($stored);

        return $stored === $names;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function threads(): array
    {
        $threads = [];
        $active = $this->discord('/guilds/'.$this->guildId().'/threads/active');

        foreach (is_array($active) ? ($active['threads'] ?? []) : [] as $thread) {
            if (is_array($thread)) {
                $threads[(string) $thread['id']] = $thread;
            }
        }

        $before = null;

        for ($page = 0; $page < 5; $page++) {
            $path = '/channels/'.$this->bugChannelId().'/threads/archived/public?limit=50';

            if ($before !== null) {
                $path .= '&before='.rawurlencode($before);
            }

            $archived = $this->discord($path);
            $batch = is_array($archived) ? ($archived['threads'] ?? []) : [];

            if (! is_array($batch) || $batch === []) {
                break;
            }

            $before = null;

            foreach ($batch as $thread) {
                if (! is_array($thread) || ! isset($thread['id'])) {
                    continue;
                }

                $threads[(string) $thread['id']] = $thread;
                $stamp = $thread['thread_metadata']['archive_timestamp'] ?? null;
                $before = is_string($stamp) && $stamp !== '' ? $stamp : null;
            }

            if (! is_array($archived) || empty($archived['has_more']) || $before === null) {
                break;
            }
        }

        return array_values($threads);
    }

    private function description(string $threadId): string
    {
        $link = 'https://discord.com/channels/'.$this->guildId().'/'.$threadId;
        $message = $this->discord('/channels/'.$threadId.'/messages/'.$threadId);
        $body = is_array($message) ? trim((string) ($message['content'] ?? '')) : '';

        if ($body === '') {
            return $link;
        }

        return mb_substr($body, 0, 4000)."\n\n".$link;
    }

    /**
     * @param  list<string>  $names
     */
    private function createCard(string $listId, string $title, string $description, array $names): ?string
    {
        $created = $this->trello('POST', '/cards', [
            'idList' => $listId,
            'name' => mb_substr($title, 0, 200),
            'desc' => $description,
            'idLabels' => implode(',', $this->labelIds($names)),
        ]);

        $id = is_array($created) ? ($created['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  list<string>  $names
     */
    private function updateLabels(string $cardId, array $names): bool
    {
        $updated = $this->trello('PUT', '/cards/'.$cardId, [
            'idLabels' => implode(',', $this->labelIds($names)),
        ]);

        return is_array($updated) && isset($updated['id']);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function labelIds(array $names): array
    {
        $existing = $this->trello('GET', '/boards/'.$this->board().'/labels');
        $byName = [];

        foreach (is_array($existing) ? $existing : [] as $label) {
            if (! is_array($label) || ! isset($label['id'])) {
                continue;
            }

            $name = trim((string) ($label['name'] ?? ''));

            if ($name !== '') {
                $byName[mb_strtolower($name, 'UTF-8')] = (string) $label['id'];
            }
        }

        $ids = [];

        foreach ($names as $name) {
            $key = mb_strtolower($name, 'UTF-8');

            if (! isset($byName[$key])) {
                $created = $this->trello('POST', '/labels', [
                    'idBoard' => $this->board(),
                    'name' => $name,
                    'color' => self::TAG_COLORS[$key] ?? 'sky',
                ]);
                $id = is_array($created) ? ($created['id'] ?? null) : null;

                if (! is_string($id) || $id === '') {
                    continue;
                }

                $byName[$key] = $id;
            }

            $ids[] = $byName[$key];
        }

        return $ids;
    }

    private function listId(): ?string
    {
        $lists = $this->trello('GET', '/boards/'.$this->board().'/lists');
        $want = mb_strtolower($this->listName(), 'UTF-8');

        foreach (is_array($lists) ? $lists : [] as $list) {
            if (! is_array($list) || ! isset($list['id'])) {
                continue;
            }

            if (mb_strtolower((string) ($list['name'] ?? ''), 'UTF-8') === $want) {
                return (string) $list['id'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function discord(string $path): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot '.$this->discordToken(),
                'User-Agent' => 'KostaaBot/1.0 (https://kostaa.space)',
            ])->timeout(12)->acceptJson()->get('https://discord.com/api/v10'.$path);
        } catch (\Throwable $exception) {
            Log::warning('Discord bug read failed.', ['path' => strtok($path, '?')]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Discord bug read was refused.', [
                'path' => strtok($path, '?'),
                'status' => $response->status(),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function trello(string $method, string $path, array $query = []): ?array
    {
        $query['key'] = $this->key();
        $query['token'] = $this->token();

        try {
            $pending = Http::timeout(12)->acceptJson()->asForm();
            $url = 'https://api.trello.com/1'.$path;
            $response = match ($method) {
                'GET' => $pending->get($url, $query),
                'PUT' => $pending->put($url, $query),
                default => $pending->post($url, $query),
            };
        } catch (\Throwable $exception) {
            Log::warning('Trello request failed.', ['path' => $path]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Trello request was refused.', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    private function discordToken(): string
    {
        return trim((string) config('blip.discord_bot_token'));
    }

    private function guildId(): string
    {
        return trim((string) config('blip.discord_guild_id'));
    }

    private function bugChannelId(): string
    {
        return trim((string) config('blip.discord_bug_channel_id'));
    }

    private function key(): string
    {
        return trim((string) config('blip.trello_key'));
    }

    private function token(): string
    {
        return trim((string) config('blip.trello_token'));
    }

    private function board(): string
    {
        return trim((string) config('blip.trello_board'));
    }

    private function listName(): string
    {
        $name = trim((string) config('blip.trello_bug_list'));

        return $name !== '' ? $name : 'To Do';
    }
}
