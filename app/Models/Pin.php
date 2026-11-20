<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

#[Fillable(['device_hash', 'message', 'category', 'kind', 'latitude', 'longitude', 'image_path', 'starts_at', 'duration_minutes', 'expires_at', 'source', 'external_id', 'placed_manually', 'suppressed_at'])]
class Pin extends Model
{
    public const DURATIONS = [
        '5m' => 5,
        '1h' => 60,
        '24h' => 60 * 24,
    ];

    public const KIND_NORMAL = 'normal';

    public const KIND_UPCOMING = 'upcoming';

    public const KIND_UNASSIGNED = 'unassigned';

    public const SAME_SPOT_METERS = 40;

    public const CATEGORIES = [
        'seeking' => 'Търся',
        'lost' => 'Загубено',
        'warning' => 'Внимание',
        'free' => 'Безплатно',
        'other' => 'Друго',
    ];

    protected $attributes = [
        'kind' => self::KIND_NORMAL,
    ];

    public static function hasActivePinNearby(string $deviceHash, float $latitude, float $longitude): bool
    {
        $pins = static::query()
            ->where('device_hash', $deviceHash)
            ->where('expires_at', '>', now())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['latitude', 'longitude']);

        foreach ($pins as $pin) {
            if ($pin->latitude === null || $pin->longitude === null) {
                continue;
            }
            if (static::metersBetween($latitude, $longitude, $pin->latitude, $pin->longitude) <= static::SAME_SPOT_METERS) {
                return true;
            }
        }

        return false;
    }

    public static function metersBetween(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earth = 6371000;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    public static function parseStartsAt(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $value)) {
                return Carbon::parse($value)->utc();
            }

            return Carbon::parse($value, 'Europe/Sofia')->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'starts_at' => 'Датата не е разпозната.',
            ]);
        }
    }

    /**
     * @return array{kind: string, starts_at: ?Carbon, duration_minutes: int, expires_at: Carbon}
     */
    public static function locatedSchedule(string $kind, ?Carbon $startsAt, string $duration): array
    {
        $minutes = self::DURATIONS[$duration] ?? null;

        if ($minutes === null) {
            throw ValidationException::withMessages([
                'duration' => 'Избери колко да стои пинът.',
            ]);
        }

        if ($kind === self::KIND_UPCOMING) {
            if ($startsAt === null) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Избери кога започва.',
                ]);
            }
            if ($startsAt->lte(now())) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Избери бъдеща дата.',
                ]);
            }
            if ($startsAt->gt(now()->addYear())) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Датата може да е най-много след една година.',
                ]);
            }

            return [
                'kind' => self::KIND_UPCOMING,
                'starts_at' => $startsAt,
                'duration_minutes' => $minutes,
                'expires_at' => $startsAt->copy()->addMinutes($minutes),
            ];
        }

        if ($kind !== self::KIND_NORMAL) {
            throw ValidationException::withMessages([
                'kind' => 'Избери сега или предстоящо.',
            ]);
        }

        return [
            'kind' => self::KIND_NORMAL,
            'starts_at' => null,
            'duration_minutes' => $minutes,
            'expires_at' => now()->addMinutes($minutes),
        ];
    }

    /**
     * @return array{kind: string, starts_at: ?Carbon, duration_minutes: int, latitude: null, longitude: null, expires_at: Carbon}
     */
    public static function unassignedAttributes(?Carbon $startsAt, string $duration): array
    {
        $minutes = self::DURATIONS[$duration] ?? null;

        if ($minutes === null) {
            throw ValidationException::withMessages([
                'duration' => 'Избери колко да стои пинът.',
            ]);
        }

        if ($startsAt !== null) {
            if ($startsAt->gt(now()->addYear())) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Датата може да е най-много след една година.',
                ]);
            }
            if ($startsAt->copy()->addMinutes($minutes)->lte(now())) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Събитието вече е приключило.',
                ]);
            }
        }

        return [
            'kind' => self::KIND_UNASSIGNED,
            'starts_at' => $startsAt,
            'duration_minutes' => $minutes,
            'latitude' => null,
            'longitude' => null,
            'expires_at' => $startsAt ? $startsAt->copy()->addMinutes($minutes) : now()->addYear(),
        ];
    }

    public function assignLocation(float $latitude, float $longitude): void
    {
        if ($this->kind !== self::KIND_UNASSIGNED) {
            throw ValidationException::withMessages([
                'pin' => 'Този пин вече има място.',
            ]);
        }

        if ($this->expires_at === null || $this->expires_at->lte(now())) {
            throw ValidationException::withMessages([
                'pin' => 'Този пин вече е скрит.',
            ]);
        }

        $minutes = $this->duration_minutes ?: self::DURATIONS['1h'];

        if ($this->starts_at !== null) {
            $expires = $this->starts_at->copy()->addMinutes($minutes);
            if ($expires->lte(now())) {
                throw ValidationException::withMessages([
                    'pin' => 'Събитието вече е приключило.',
                ]);
            }
            $this->kind = $this->starts_at->isFuture() ? self::KIND_UPCOMING : self::KIND_NORMAL;
            $this->expires_at = $expires;
        } else {
            $this->kind = self::KIND_NORMAL;
            $this->starts_at = null;
            $this->expires_at = now()->addMinutes($minutes);
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->placed_manually = true;
        $this->save();
    }

    public function isPublic(): bool
    {
        return $this->kind !== self::KIND_UNASSIGNED
            && $this->latitude !== null
            && $this->longitude !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopeOnMap(Builder $query): Builder
    {
        return $query
            ->where('kind', '!=', self::KIND_UNASSIGNED)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('expires_at', '>', now());
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'expires_at' => 'datetime',
            'placed_manually' => 'boolean',
            'suppressed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_hash', 'device_hash');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * @return array{id: int, message: string, category: ?string, kind: string, latitude: ?float, longitude: ?float, image_url: ?string, starts_at: ?string, duration_minutes: ?int, expires_at: string, created_at: string, mine: bool, replied: bool, reported: bool}
     */
    public function toApi(bool $mine, bool $replied = false, bool $reported = false): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'category' => $this->category,
            'kind' => $this->kind,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->imageUrl(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'mine' => $mine,
            'replied' => $replied,
            'reported' => $reported,
        ];
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return rtrim(request()->getSchemeAndHttpHost(), '/').'/storage/'.$this->image_path;
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }

    public static function pruneExpired(): int
    {
        $pins = static::query()
            ->where('expires_at', '<=', now())
            ->whereNotNull('image_path')
            ->limit(100)
            ->get();

        foreach ($pins as $pin) {
            $pin->deleteImage();
            $pin->forceFill(['image_path' => null])->save();
        }

        return $pins->count();
    }
}
