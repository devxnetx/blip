<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['device_hash', 'label', 'banned_at', 'first_seen_at', 'last_seen_at'])]
class Device extends Model
{
    public static function hashForAdmin(int $userId): string
    {
        return hash('sha256', 'kostaa-admin:'.$userId);
    }

    public static function record(string $hash, ?string $label = null): self
    {
        $device = static::query()->firstOrCreate(
            ['device_hash' => $hash],
            [
                'label' => $label,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        $updates = ['last_seen_at' => now()];

        if ($label !== null && ($device->label === null || $device->label === '')) {
            $updates['label'] = $label;
        }

        $device->forceFill($updates)->save();

        return $device;
    }

    /**
     * @return list<int>
     */
    public function ban(): array
    {
        $ids = $this->pins()
            ->where('expires_at', '>', now())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids !== []) {
            Pin::query()->whereIn('id', $ids)->update(['expires_at' => now()]);
        }

        if ($this->banned_at === null) {
            $this->forceFill(['banned_at' => now()])->save();
        }

        return $ids;
    }

    public function unban(): void
    {
        $this->forceFill(['banned_at' => null])->save();
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function isImporter(): bool
    {
        foreach (array_keys(PinImport::LABELS) as $source) {
            if (hash_equals(hash('sha256', 'kostaa-import:'.$source), $this->device_hash)) {
                return true;
            }
        }

        return false;
    }

    public function shortHash(): string
    {
        return substr($this->device_hash, 0, 8);
    }

    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class, 'device_hash', 'device_hash');
    }

    protected function casts(): array
    {
        return [
            'banned_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
