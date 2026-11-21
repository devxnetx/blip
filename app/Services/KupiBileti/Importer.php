<?php

namespace App\Services\KupiBileti;

use App\Models\Category;
use App\Models\Device;
use App\Models\Pin;
use App\Services\ImportHalted;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Importer
{
    public const SOURCE = 'kupibileti';

    public function __construct(
        private Client $pages,
        private NominatimGeocoder $geocoder,
    ) {}

    /**
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function run(?callable $progress = null): array
    {
        $hash = hash('sha256', 'kostaa-import:kupibileti');
        Device::record($hash, 'Купи билети');
        $this->categories();

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];

        try {
            $urls = $this->pages->eventUrls();
        } catch (RuntimeException $exception) {
            throw new ImportHalted($exception->getMessage(), $counts);
        }

        foreach ($urls as $url) {
            try {
                $html = $this->pages->get($url);

                if (! is_string($html)) {
                    if ($progress !== null) {
                        $progress('empty '.$url);
                    }
                    continue;
                }

                $event = EventPage::parse($html, $url);

                if ($event === null || isset($seen[$event->slug])) {
                    if ($progress !== null) {
                        $progress('skipped '.$url);
                    }
                    $counts['skipped']++;

                    continue;
                }

                $seen[$event->slug] = true;
                $outcome = $this->store($event, $hash);
                $counts[$outcome ?? 'skipped']++;

                if ($progress !== null) {
                    $progress(($outcome ?? 'skipped').' '.$event->slug);
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

        $minutes = (int) round(($event->endsAt->getTimestamp() - $event->startsAt->getTimestamp()) / 60);
        $minutes = max(1, min(65535, $minutes));
        $expiresAt = $minutes === 65535 && $event->endsAt->greaterThan($event->startsAt->copy()->addMinutes(65535))
            ? $event->startsAt->copy()->addMinutes(65535)
            : $event->endsAt;
        $point = null;

        if ($existing?->placed_manually) {
            $point = [
                'latitude' => $existing->latitude,
                'longitude' => $existing->longitude,
            ];
        } else {
            $point = $this->resolve($event);
        }

        if ($point === null && $existing !== null && $existing->latitude !== null && $existing->longitude !== null && ! $existing->placed_manually) {
            $point = [
                'latitude' => (float) $existing->latitude,
                'longitude' => (float) $existing->longitude,
            ];
        }

        $kind = Pin::KIND_UNASSIGNED;
        $outcome = 'unassigned';

        if ($point !== null && $point['latitude'] !== null && $event->startsAt->isFuture()) {
            $kind = Pin::KIND_UPCOMING;
            $outcome = 'upcoming';
        } elseif ($point !== null && $point['latitude'] !== null) {
            $kind = Pin::KIND_NORMAL;
            $outcome = 'current';
        }

        $attributes = [
            'device_hash' => $existing->device_hash ?? $deviceHash,
            'message' => $event->title,
            'description' => $event->description,
            'category' => $event->category,
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
        } elseif ($point !== null && $point['latitude'] !== null && $point['longitude'] !== null) {
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

    /**
     * @return array{latitude: float|null, longitude: float|null}|null
     */
    private function resolve(ParsedEvent $event): ?array
    {
        $address = $event->address ?? $event->city;

        if ($address !== null || $event->venue !== null) {
            $point = $this->geocoder->locate($address, $event->venue);

            if ($point !== null) {
                return $point;
            }
        }

        if ($event->address !== null) {
            $point = $this->geocoder->locate($event->address, null);

            if ($point !== null) {
                return $point;
            }
        }

        foreach ($this->placeWords($event->address, $event->city) as $word) {
            $line = $event->city !== null && $event->city !== '' ? $word.', '.$event->city : $word;
            $point = $this->geocoder->locate($line, null);

            if ($point !== null) {
                return $point;
            }
        }

        return null;
    }

    /**
     * A landmark written after the street, with the article taken off when the map knows the bare word.
     *
     * @return list<string>
     */
    private function placeWords(?string $address, ?string $city): array
    {
        if ($address === null || $address === '') {
            return [];
        }

        $words = [];

        foreach (preg_split('/\s*,\s*/u', $address) ?: [] as $part) {
            $part = trim($part);

            if ($part === '' || preg_match('/^\d{4}$/u', $part) === 1) {
                continue;
            }

            if (preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|булевард|пл\.|площад)\s*\S/ui', $part) === 1) {
                continue;
            }

            if ($city !== null && mb_strtolower($part) === mb_strtolower($city)) {
                continue;
            }

            $words[$part] = true;
            $bare = $part;

            if (preg_match('/^(.+?)(а|я)$/u', $part, $match) === 1 && mb_strlen($match[1]) >= 5) {
                $bare = $match[1];
            }

            if ($bare !== $part) {
                $words[$bare] = true;
            }
        }

        return array_keys($words);
    }

    private function categories(): void
    {
        $this->ensure('event', 'Събитие', false);
        $this->ensure('sport', 'Спорт', true);
        $this->ensure('art', 'Арт', true);
    }

    private function ensure(string $slug, string $name, bool $listed): void
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'position' => ((int) Category::query()->max('position')) + 1,
                'listed' => $listed,
            ],
        );

        if ($listed && ! $category->listed) {
            $category->forceFill(['listed' => true])->save();
        }
    }

    private function image(ParsedEvent $event, ?Pin $existing): ?string
    {
        if ($event->imageUrl === null) {
            return null;
        }

        $key = 'kupibileti-image:'.$event->slug;

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

        $path = 'pins/kupibileti-'.$event->slug.'.jpg';

        if ($existing?->image_path && $existing->image_path !== $path) {
            $existing->deleteImage();
        }

        Storage::disk('public')->put($path, $jpeg);
        Cache::put($key, $event->imageUrl, now()->addDays(30));

        return $path;
    }
}
