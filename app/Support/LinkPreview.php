<?php

namespace App\Support;

use App\Models\Pin;
use Illuminate\Support\Str;

class LinkPreview
{
    public const FALLBACK_DESCRIPTION = 'Kostaa е приложение за събития. Ела да видиш какво се случва в момента.';

    /**
     * @return array{title: string, description: string, image: string, image_type: string, url: string}
     */
    public static function page(string $title, string $description, string $url): array
    {
        return [
            'title' => $title,
            'description' => self::clean($description, 200),
            'image' => self::logo(),
            'image_type' => 'image/png',
            'url' => $url,
        ];
    }

    /**
     * @return array{title: string, description: string, image: string, image_type: string, url: string}
     */
    public static function forPin(?Pin $pin): array
    {
        $page = self::page('Карта — Kostaa', self::FALLBACK_DESCRIPTION, self::root().'/map');

        if ($pin === null || ! $pin->isPublic() || $pin->suppressed_at !== null) {
            return $page;
        }

        $description = self::clean((string) $pin->description, 200);
        $image = $pin->imageUrl() ?: self::logo();

        return [
            'title' => $pin->message.' — Kostaa',
            'description' => $description !== '' ? $description : self::FALLBACK_DESCRIPTION,
            'image' => $image,
            'image_type' => self::imageType($image),
            'url' => self::root().'/map?pin='.$pin->id,
        ];
    }

    public static function logo(): string
    {
        return self::root().'/brand/banner-og.png';
    }

    private static function root(): string
    {
        return rtrim(request()->getSchemeAndHttpHost(), '/');
    }

    private static function clean(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

        return $value === '' ? '' : Str::limit($value, $limit, '…');
    }

    private static function imageType(?string $url): string
    {
        $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));

        return match (true) {
            str_ends_with($path, '.png') => 'image/png',
            str_ends_with($path, '.webp') => 'image/webp',
            str_ends_with($path, '.gif') => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
