<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['device_hash', 'message', 'category', 'latitude', 'longitude', 'image_path', 'expires_at'])]
class Pin extends Model
{
    public const DURATIONS = [
        '5m' => 5,
        '1h' => 60,
        '24h' => 60 * 24,
    ];

    public const SAME_SPOT_METERS = 40;

    public const CATEGORIES = [
        'seeking' => 'Търся',
        'lost' => 'Загубено',
        'warning' => 'Внимание',
        'free' => 'Безплатно',
        'other' => 'Друго',
    ];

    public static function hasActivePinNearby(string $deviceHash, float $latitude, float $longitude): bool
    {
        $pins = static::query()
            ->where('device_hash', $deviceHash)
            ->where('expires_at', '>', now())
            ->get(['latitude', 'longitude']);

        foreach ($pins as $pin) {
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

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'expires_at' => 'datetime',
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
     * @return array{id: int, message: string, category: ?string, latitude: float, longitude: float, image_url: ?string, expires_at: string, created_at: string, mine: bool, replied: bool, reported: bool}
     */
    public function toApi(bool $mine, bool $replied = false, bool $reported = false): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'category' => $this->category,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->imageUrl(),
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
