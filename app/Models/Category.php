<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

#[Fillable(['slug', 'name', 'position', 'listed'])]
class Category extends Model
{
    protected $attributes = [
        'listed' => true,
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        try {
            if (! Schema::hasTable('categories')) {
                return Pin::CATEGORIES;
            }
        } catch (\Throwable) {
            return Pin::CATEGORIES;
        }

        $options = static::query()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('name', 'slug')
            ->all();

        return $options !== [] ? $options : Pin::CATEGORIES;
    }

    /**
     * Categories a person can pick. Imported sports stay off this list.
     *
     * @return array<string, string>
     */
    public static function listedOptions(): array
    {
        try {
            if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories', 'listed')) {
                return static::options();
            }
        } catch (\Throwable) {
            return static::options();
        }

        $options = static::query()
            ->where('listed', true)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('name', 'slug')
            ->all();

        return $options !== [] ? $options : static::options();
    }

    public static function label(?string $slug): string
    {
        if ($slug === null || $slug === '') {
            return '—';
        }

        return static::options()[$slug] ?? $slug;
    }

    public static function uniqueSlug(string $name): string
    {
        $base = trim(Str::slug(Str::ascii($name)), '-');

        if ($base === '') {
            $base = 'kategoria';
        }

        $base = substr($base, 0, 40);
        $slug = $base;
        $n = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $suffix = '-'.$n;
            $slug = substr($base, 0, 40 - strlen($suffix)).$suffix;
            $n++;
        }

        return $slug;
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'listed' => 'boolean',
        ];
    }

    public function pins(): HasMany
    {
        return $this->hasMany(Pin::class, 'category', 'slug');
    }
}
