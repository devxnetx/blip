<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::query()->where('key', $key)->value('value');

        return is_string($value) ? $value : $default;
    }

    public static function put(string $key, string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function enabled(string $key, bool $default = true): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return $value === '1';
    }

    public static function daysAhead(): int
    {
        $raw = self::get('import_days_ahead');
        $days = $raw === null ? (int) config('blip.eventibg_days', 14) : (int) $raw;

        return max(1, min(90, $days > 0 ? $days : 14));
    }

    public static function importUntil(): Carbon
    {
        return now('Europe/Sofia')->startOfDay()->addDays(self::daysAhead())->utc();
    }
}
