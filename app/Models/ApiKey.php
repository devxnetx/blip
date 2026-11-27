<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['name', 'token_prefix', 'token_hash', 'last_used_at', 'revoked_at'])]
class ApiKey extends Model
{
    /**
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name): array
    {
        $plain = 'kst_'.Str::random(40);

        $key = static::query()->create([
            'name' => $name,
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
        ]);

        return [$key, $plain];
    }

    public static function findActive(string $plain): ?self
    {
        if ($plain === '' || ! str_starts_with($plain, 'kst_')) {
            return null;
        }

        $key = static::query()->where('token_hash', hash('sha256', $plain))->first();

        if ($key === null || $key->revoked_at !== null) {
            return null;
        }

        return $key;
    }

    public function revoke(): void
    {
        if ($this->revoked_at === null) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
