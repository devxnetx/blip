<?php

namespace App\Services\SportenKalendar;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Models\Setting;
use App\Services\ImportHalted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Importer
{
    public const SOURCE = 'sportenkalendar';

    public function __construct(
        private CalendarClient $pages,
        private EventPageParser $parser,
        private NominatimGeocoder $geocoder,
    ) {}

    /**
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function run(?callable $progress = null, ?int $limit = null): array
    {
        $hash = hash('sha256', 'kostaa-import:sportenkalendar');
        Device::record($hash, 'Спортен календар');

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];

        $urls = $this->pages->eventUrls($progress);

        if ($limit !== null) {
            $urls = array_slice($urls, 0, $limit);
        }

        foreach ($urls as $url) {
            try {
                $html = $this->pages->get($url);
                $event = is_string($html) ? $this->parser->parse($html, $url) : null;

                if ($event === null) {
                    $counts['skipped']++;
                    if ($progress !== null) {
                        $progress('skipped '.$url);
                    }
                    continue;
                }

                $outcome = $this->store($event, $hash);

                if ($outcome === null) {
                    $counts['skipped']++;
                } else {
                    $counts[$outcome]++;
                }

                if ($progress !== null) {
                    $progress(($outcome ?? 'skipped').' '.$event->externalId.' '.$event->title);
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
            ->where('external_id', $event->externalId)
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

        $minutes = (int) min(65535, max(1, (int) round(($event->endsAt->getTimestamp() - $event->startsAt->getTimestamp()) / 60)));
        $expiresAt = $event->startsAt->copy()->addMinutes($minutes);
        $point = null;

        if ($existing?->placed_manually) {
            $point = [
                'latitude' => $existing->latitude,
                'longitude' => $existing->longitude,
            ];
        } elseif (is_string($event->address) && $event->address !== '') {
            $point = $this->geocoder->locate($event->address);
        }

        if ($point === null && $existing !== null && $existing->latitude !== null && $existing->longitude !== null && ! $existing->placed_manually) {
            $point = [
                'latitude' => $existing->latitude,
                'longitude' => $existing->longitude,
            ];
        }

        $kind = Pin::KIND_UNASSIGNED;
        $outcome = 'unassigned';

        if ($point !== null && $event->startsAt->isFuture()) {
            $kind = Pin::KIND_UPCOMING;
            $outcome = 'upcoming';
        } elseif ($point !== null) {
            $kind = Pin::KIND_NORMAL;
            $outcome = 'current';
        }

        $attributes = [
            'device_hash' => $existing->device_hash ?? $deviceHash,
            'message' => $event->title,
            'category' => $this->categorySlug($event),
            'kind' => $existing?->placed_manually ? $existing->kind : $kind,
            'starts_at' => $event->startsAt,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt,
            'source' => self::SOURCE,
            'external_id' => $event->externalId,
            'source_url' => $event->url,
        ];

        if ($existing?->placed_manually) {
            $attributes['latitude'] = $existing->latitude;
            $attributes['longitude'] = $existing->longitude;
            $outcome = $existing->kind === Pin::KIND_UPCOMING ? 'upcoming' : ($existing->latitude === null ? 'unassigned' : 'current');
        } elseif ($point !== null) {
            $attributes['latitude'] = $point['latitude'];
            $attributes['longitude'] = $point['longitude'];
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

    private function categorySlug(ParsedEvent $event): ?string
    {
        if ($event->sportName === null || $event->sportName === '') {
            return null;
        }

        if (is_string($event->sportSlug)) {
            $bySlug = Category::query()->where('slug', $event->sportSlug)->first();

            if ($bySlug !== null) {
                return $bySlug->slug;
            }
        }

        $byName = Category::query()->where('name', $event->sportName)->first();

        if ($byName !== null) {
            return $byName->slug;
        }

        $slug = is_string($event->sportSlug) && preg_match('/^[a-z0-9\-]{1,40}$/', $event->sportSlug)
            ? $event->sportSlug
            : Category::uniqueSlug($event->sportName);

        if (Category::query()->where('slug', $slug)->exists()) {
            $slug = Category::uniqueSlug($event->sportName);
        }

        $position = ((int) Category::query()->max('position')) + 1;

        return Category::query()->create([
            'slug' => $slug,
            'name' => $event->sportName,
            'position' => $position,
            'listed' => false,
        ])->slug;
    }

    private function image(ParsedEvent $event, ?Pin $existing): ?string
    {
        if ($event->imageUrl === null) {
            return null;
        }

        $key = 'sporten-image:'.$event->externalId;

        if ($existing?->image_path && Cache::get($key) === $event->imageUrl && Storage::disk('public')->exists($existing->image_path)) {
            return $existing->image_path;
        }

        try {
            $bytes = $this->pages->get($event->imageUrl);
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

        $path = 'pins/sporten-'.$event->externalId.'.jpg';

        if ($existing?->image_path && $existing->image_path !== $path) {
            $existing->deleteImage();
        }

        Storage::disk('public')->put($path, $jpeg);
        Cache::put($key, $event->imageUrl, now()->addDays(14));

        return $path;
    }
}
