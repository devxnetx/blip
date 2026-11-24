<?php

namespace App\Services\FacebookEvents;

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
    public const SOURCE = 'facebook';

    public function __construct(private Client $events) {}

    private bool $openWindow = false;

    private bool $wideMatch = false;

    /**
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function run(?callable $progress = null): array
    {
        $hash = hash('sha256', 'kostaa-import:facebook');
        Device::record($hash, 'Facebook събития');

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];
        $this->openWindow = false;
        $this->wideMatch = false;
        $this->eventCategory();
        $cities = $this->cities();
        $remaining = max(0, (int) config('blip.facebook_events_max_total', 10000));
        $extracted = 0;
        $left = count($cities);

        foreach ($cities as $city) {
            if ($remaining < 1 || $left < 1) {
                break;
            }

            $allowance = intdiv($remaining, $left);
            $left--;

            if ($allowance < 1) {
                $allowance = 1;
            }

            $allowance = min($allowance, $remaining);

            try {
                $rows = $this->events->events($city, $allowance);
            } catch (RuntimeException $exception) {
                throw new ImportHalted($exception->getMessage(), $counts);
            }

            $returned = count($rows);

            if ($returned > $allowance) {
                $rows = array_slice($rows, 0, $allowance);
            }

            $extracted += $returned;
            $remaining = max(0, $remaining - $returned);

            if ($progress !== null) {
                $progress(sprintf(
                    'EXTRACTED %s added=%d total=%d',
                    $city['name'],
                    $returned,
                    $extracted,
                ));
            }

            $this->absorb($rows, $city, $hash, $counts, $seen, $progress);
        }

        return $counts;
    }

    /**
     * One city, with the date window off and both the city page and the keyword search.
     *
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function runWide(string $slug, ?callable $progress = null): array
    {
        $hash = hash('sha256', 'kostaa-import:facebook');
        Device::record($hash, 'Facebook събития');
        $this->eventCategory();
        $this->openWindow = true;
        $this->wideMatch = true;

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];
        $city = $this->cityBySlug($slug);

        try {
            $rows = $this->events->wide($city, $this->wideQueries($city), 0, $progress);
        } catch (RuntimeException $exception) {
            throw new ImportHalted($exception->getMessage(), $counts);
        }

        return $this->saveWideRows($city, $rows, $hash, $counts, $seen, $progress, count($rows));
    }

    /**
     * Wide search for every map city. Stops before the event cap is passed.
     *
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function runWideAll(?callable $progress = null): array
    {
        $hash = hash('sha256', 'kostaa-import:facebook');
        Device::record($hash, 'Facebook събития');
        $this->eventCategory();
        $this->openWindow = true;
        $this->wideMatch = true;

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];
        $cities = $this->cities();
        $remaining = max(0, (int) config('blip.facebook_events_max_total', 10000));
        $extracted = 0;
        $left = count($cities);

        foreach ($cities as $city) {
            if ($remaining < 1 || $left < 1) {
                if ($progress !== null) {
                    $progress(sprintf('BUDGET %s stopped total=%d', $city['name'], $extracted));
                }

                break;
            }

            $allowance = intdiv($remaining, $left);
            $left--;

            if ($allowance < 1) {
                $allowance = 1;
            }

            $allowance = min($allowance, $remaining);

            try {
                $rows = $this->events->wide($city, $this->wideQueries($city), $allowance, $progress);
            } catch (RuntimeException $exception) {
                throw new ImportHalted($exception->getMessage(), $counts);
            }

            $returned = count($rows);

            if ($returned > $allowance) {
                $rows = array_slice($rows, 0, $allowance);
            }

            $extracted += $returned;
            $remaining = max(0, $remaining - $returned);
            $counts = $this->saveWideRows($city, $rows, $hash, $counts, $seen, $progress, $extracted);
        }

        return $counts;
    }

    /**
     * @param  array{query?: string, name?: string}  $city
     * @return list<string>
     */
    private function wideQueries(array $city): array
    {
        return array_values(array_unique(array_filter([
            (string) ($city['query'] ?? ''),
            (string) ($city['name'] ?? ''),
            trim((string) ($city['name'] ?? '').' България'),
        ], fn (string $query): bool => $query !== '')));
    }

    /**
     * Store rows already collected for one city. Does not start another actor run.
     *
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    public function saveCollectedRun(string $slug, string $runId, ?callable $progress = null): array
    {
        $hash = hash('sha256', 'kostaa-import:facebook');
        Device::record($hash, 'Facebook събития');
        $this->eventCategory();
        $this->openWindow = true;
        $this->wideMatch = true;

        $counts = [
            'upcoming' => 0,
            'current' => 0,
            'unassigned' => 0,
            'skipped' => 0,
            'hidden' => 0,
        ];
        $seen = [];
        $city = $this->cityBySlug($slug);

        try {
            $rows = $this->events->collectRun($runId, $progress);
        } catch (RuntimeException $exception) {
            throw new ImportHalted($exception->getMessage(), $counts);
        }

        return $this->saveWideRows($city, $rows, $hash, $counts, $seen, $progress, count($rows));
    }

    /**
     * @param  array{name: string, url: string, query: string, city_id: string, latitude: float, longitude: float, match: list<string>}  $city
     * @param  list<array<string, mixed>>  $rows
     * @param  array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}  $counts
     * @param  array<string, true>  $seen
     * @param  (callable(string): void)|null  $progress
     * @return array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}
     */
    private function saveWideRows(array $city, array $rows, string $hash, array &$counts, array &$seen, ?callable $progress, int $total): array
    {
        if ($progress !== null) {
            $progress(sprintf(
                'EXTRACTED %s added=%d total=%d',
                $city['name'],
                count($rows),
                $total,
            ));
        }

        $this->absorb($rows, $city, $hash, $counts, $seen, $progress);

        return $counts;
    }

    /**
     * @return list<array{name: string, url: string, query: string, city_id: string, latitude: float, longitude: float, match: list<string>}>
     */
    private function cities(): array
    {
        $rows = config('blip.facebook_cities', []);
        $cities = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! is_string($row['url'] ?? null) || ! is_string($row['query'] ?? null)) {
                continue;
            }

            $cities[] = $row;
        }

        return $cities;
    }

    /**
     * @param  array{city_id?: string, match?: list<string>}  $city
     */
    private function belongs(ParsedEvent $event, array $city): bool
    {
        $cityId = (string) ($city['city_id'] ?? '');

        if ($cityId !== '' && $event->cityId === $cityId) {
            return true;
        }

        $label = mb_strtolower(trim(($event->cityLabel ?? '').' '.($event->address ?? '').' '.($event->venue ?? '')));

        foreach ($city['match'] ?? [] as $needle) {
            $needle = mb_strtolower((string) $needle);

            if ($needle !== '' && preg_match('/(?:^|[^\p{L}\p{N}])'.preg_quote($needle, '/').'(?:$|[^\p{L}\p{N}])/u', $label) === 1) {
                return true;
            }
        }

        if ($this->wideMatch && $this->nearCity($event, $city)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{latitude?: float, longitude?: float}  $city
     */
    private function nearCity(ParsedEvent $event, array $city): bool
    {
        if ($event->latitude === null || $event->longitude === null) {
            return false;
        }

        $latitude = $city['latitude'] ?? null;
        $longitude = $city['longitude'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return false;
        }

        return Pin::metersBetween($event->latitude, $event->longitude, (float) $latitude, (float) $longitude) <= 25000;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{name?: string, city_id?: string, match?: list<string>, latitude?: float, longitude?: float}  $city
     * @param  array{upcoming: int, current: int, unassigned: int, skipped: int, hidden: int}  $counts
     * @param  array<string, true>  $seen
     * @param  (callable(string): void)|null  $progress
     */
    private function absorb(array $rows, array $city, string $hash, array &$counts, array &$seen, ?callable $progress): void
    {
        foreach ($rows as $row) {
            $event = EventRecord::from($row);

            if ($event === null || isset($seen[$event->id]) || ! $this->belongs($event, $city)) {
                if ($event !== null && ! isset($seen[$event->id]) && ! $this->belongs($event, $city)) {
                    $seen[$event->id] = true;
                    $counts['skipped']++;
                }

                continue;
            }

            $seen[$event->id] = true;

            try {
                $outcome = $this->store($event, $hash);
            } catch (RuntimeException $exception) {
                throw new ImportHalted($exception->getMessage(), $counts);
            }

            $counts[$outcome ?? 'skipped']++;

            if ($progress !== null) {
                $progress(($outcome ?? 'skipped').' '.$event->id);
            }
        }
    }

    /**
     * @return array{name: string, url: string, query: string, city_id: string, latitude: float, longitude: float, match: list<string>}
     */
    private function cityBySlug(string $slug): array
    {
        $needle = mb_strtolower(trim($slug));

        foreach ($this->cities() as $city) {
            $name = mb_strtolower((string) ($city['name'] ?? ''));
            $url = (string) ($city['url'] ?? '');

            if ($needle !== '' && ($needle === $name || preg_match('#/events/explore/'.preg_quote($needle, '#').'-bulgaria/\d+#', $url) === 1)) {
                return $city;
            }
        }

        throw new RuntimeException('That city is not on the map.');
    }

    private function store(ParsedEvent $event, string $deviceHash): ?string
    {
        $existing = Pin::query()
            ->where('source', self::SOURCE)
            ->where('external_id', $event->id)
            ->first();

        if ($existing?->suppressed_at !== null || $event->canceled || $event->online) {
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

        if (! $this->openWindow && ($event->startsAt->greaterThan($now->copy()->addYear()) || $event->startsAt->greaterThanOrEqualTo(Setting::importUntil()))) {
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
        } elseif ($event->latitude !== null && $event->longitude !== null) {
            $point = [
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
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
            'description' => $event->description,
            'category' => 'event',
            'kind' => $existing?->placed_manually ? $existing->kind : $kind,
            'starts_at' => $event->startsAt,
            'duration_minutes' => $minutes,
            'expires_at' => $expiresAt,
            'source' => self::SOURCE,
            'external_id' => $event->id,
            'source_url' => $event->url,
        ];

        if ($existing?->placed_manually) {
            $attributes['latitude'] = $existing->latitude;
            $attributes['longitude'] = $existing->longitude;
            $outcome = $existing->kind === Pin::KIND_UPCOMING
                ? 'upcoming'
                : ($existing->latitude === null ? 'unassigned' : 'current');
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

        $key = 'facebook-image:'.$event->id;

        if ($existing?->image_path && Cache::get($key) === $event->imageUrl && Storage::disk('public')->exists($existing->image_path)) {
            return $existing->image_path;
        }

        try {
            $bytes = $this->events->image($event->imageUrl);
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

        $path = 'pins/facebook-'.$event->id.'.jpg';

        if ($existing?->image_path && $existing->image_path !== $path) {
            $existing->deleteImage();
        }

        Storage::disk('public')->put($path, $jpeg);
        Cache::put($key, $event->imageUrl, now()->addDays(14));

        return $path;
    }
}
