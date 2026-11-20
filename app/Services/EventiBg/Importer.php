<?php

namespace App\Services\EventiBg;

use App\Models\Device;
use App\Models\Pin;
use App\Models\Setting;
use App\Services\ImportHalted;
use RuntimeException;

class Importer
{
    public const SOURCE = 'eventibg';

    public function __construct(
        private DayClient $pages,
        private DayParser $parser,
    ) {}

    /**
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function run(?callable $progress = null, ?int $days = null): array
    {
        $hash = hash('sha256', 'kostaa-import:eventibg');
        Device::record($hash, 'EventiBG');

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];

        foreach ($this->pages->dayUrls($days) as $url) {
            try {
                $html = $this->pages->get($url);

                if (! is_string($html)) {
                    if ($progress !== null) {
                        $progress('empty '.$url);
                    }
                    continue;
                }

                foreach ($this->parser->parse($html) as $event) {
                    if (isset($seen[$event->slug])) {
                        continue;
                    }

                    $seen[$event->slug] = true;
                    $outcome = $this->store($event, $hash);
                    $counts[$outcome ?? 'skipped']++;

                    if ($progress !== null) {
                        $progress(($outcome ?? 'skipped').' '.$event->slug);
                    }
                }
            } catch (RuntimeException $exception) {
                throw new ImportHalted($exception->getMessage(), $counts);
            }
        }

        return $counts;
    }

    private function store(ParsedEvent $event, string $deviceHash): ?string
    {
        $existing = Pin::query()
            ->where('source', self::SOURCE)
            ->where('external_id', $event->slug)
            ->first();

        if ($existing?->suppressed_at !== null) {
            return 'skipped';
        }

        $now = now();

        if ($event->endsAt->lessThanOrEqualTo($now)) {
            if ($existing !== null && $existing->expires_at !== null && $existing->expires_at->isFuture()) {
                $existing->forceFill(['expires_at' => $event->endsAt])->save();

                return 'hidden';
            }

            return 'skipped';
        }

        if ($event->startsAt->greaterThan($now->copy()->addYear()) || $event->startsAt->greaterThanOrEqualTo(Setting::importUntil())) {
            return 'skipped';
        }

        $minutes = (int) round(($event->endsAt->getTimestamp() - $event->startsAt->getTimestamp()) / 60);
        $minutes = max(1, min(65535, $minutes));
        $expiresAt = $minutes === 65535 && $event->endsAt->greaterThan($event->startsAt->copy()->addMinutes(65535))
            ? $event->startsAt->copy()->addMinutes(65535)
            : $event->endsAt;
        $point = $event->latitude !== null && $event->longitude !== null;

        if ($existing?->placed_manually) {
            $point = $existing->latitude !== null && $existing->longitude !== null;
        }

        $kind = Pin::KIND_UNASSIGNED;
        $outcome = 'unassigned';

        if ($point && $event->startsAt->isFuture()) {
            $kind = Pin::KIND_UPCOMING;
            $outcome = 'upcoming';
        } elseif ($point) {
            $kind = Pin::KIND_NORMAL;
            $outcome = 'current';
        }

        $attributes = [
            'device_hash' => $existing->device_hash ?? $deviceHash,
            'message' => $event->title,
            'category' => 'other',
            'kind' => $existing?->placed_manually ? $existing->kind : $kind,
            'starts_at' => $event->startsAt,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt,
            'source' => self::SOURCE,
            'external_id' => $event->slug,
            'source_url' => $event->url,
        ];

        if ($existing?->placed_manually) {
            $attributes['latitude'] = $existing->latitude;
            $attributes['longitude'] = $existing->longitude;
            $outcome = $existing->kind === Pin::KIND_UPCOMING
                ? 'upcoming'
                : ($existing->latitude === null ? 'unassigned' : 'current');
        } elseif ($event->latitude !== null && $event->longitude !== null) {
            $attributes['latitude'] = $event->latitude;
            $attributes['longitude'] = $event->longitude;
        } else {
            $attributes['latitude'] = null;
            $attributes['longitude'] = null;
            $attributes['kind'] = Pin::KIND_UNASSIGNED;
            $outcome = 'unassigned';
        }

        if ($existing === null) {
            Pin::query()->create($attributes);
        } else {
            $existing->forceFill($attributes)->save();
        }

        return $outcome;
    }
}
