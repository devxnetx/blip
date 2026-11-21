<?php

namespace App\Services\EventiBg;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\Setting;
use App\Services\ImportHalted;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Importer
{
    public const SOURCE = 'eventibg';

    public function __construct(
        private DayClient $pages,
        private DayParser $parser,
        private NominatimGeocoder $geocoder,
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
        $this->eventCategory();

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
        $resolved = $existing?->placed_manually ? null : $this->resolve($event);
        $point = $resolved !== null || ($event->latitude !== null && $event->longitude !== null);

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
            'description' => $event->description,
            'category' => 'event',
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
        } elseif ($resolved !== null) {
            $attributes['latitude'] = $resolved['latitude'];
            $attributes['longitude'] = $resolved['longitude'];
        } elseif ($event->latitude !== null && $event->longitude !== null) {
            $attributes['latitude'] = $event->latitude;
            $attributes['longitude'] = $event->longitude;
        } else {
            $attributes['latitude'] = null;
            $attributes['longitude'] = null;
            $attributes['kind'] = Pin::KIND_UNASSIGNED;
            $outcome = 'unassigned';
        }

        $imagePath = $this->image($event, $existing);

        if ($imagePath !== null) {
            $attributes['image_path'] = $imagePath;
        }

        if ($existing === null) {
            Pin::query()->create($attributes);
        } else {
            $existing->forceFill($attributes)->save();
        }

        return $outcome;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function resolve(ParsedEvent $event): ?array
    {
        $place = PlaceLine::fromDescription($event->description);

        if ($place === null) {
            return null;
        }

        return $this->geocoder->locate($place['address'], $place['venue']);
    }

    private function eventCategory(): void
    {
        Category::query()->firstOrCreate(
            ['slug' => 'event'],
            [
                'name' => 'Събитие',
                'position' => ((int) Category::query()->max('position')) + 1,
                'listed' => false,
            ],
        );
    }

    private function image(ParsedEvent $event, ?Pin $existing): ?string
    {
        if ($event->imageUrl === null) {
            return null;
        }

        $key = 'eventibg-image:'.$event->slug;

        if ($existing?->image_path && Cache::get($key) === $event->imageUrl && Storage::disk('public')->exists($existing->image_path)) {
            return $existing->image_path;
        }

        try {
            $bytes = $this->pages->image($event->imageUrl);
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'slow down')) {
                throw $exception;
            }

            return null;
        }

        if (! is_string($bytes) || strlen($bytes) < 32 || strlen($bytes) > 5_000_000) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1200;

        if ($width > $max || $height > $max) {
            $scale = min($max / $width, $max / $height);
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagedestroy($image);
            $image = $resized;
        }

        if ($image === false) {
            return null;
        }

        ob_start();
        imagejpeg($image, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        if (! is_string($jpeg) || $jpeg === '') {
            return null;
        }

        $path = 'pins/eventibg-'.$event->slug.'.jpg';

        if ($existing?->image_path && $existing->image_path !== $path) {
            $existing->deleteImage();
        }

        Storage::disk('public')->put($path, $jpeg);
        Cache::put($key, $event->imageUrl, now()->addDays(14));

        return $path;
    }
}
