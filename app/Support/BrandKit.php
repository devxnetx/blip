<?php

namespace App\Support;

class BrandKit
{
    /**
     * Downloadable marks. The pin matches the website loading screen.
     *
     * @return list<array{title: string, text: string, kind: string, items: list<array{label: string, path: string, size: string, width: ?int, height: ?int}>}>
     */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Икона',
                'text' => 'Оранжевият пин от началния екран. Фонът е прозрачен, затова стои върху всеки цвят. Същият пин е favicon.',
                'kind' => 'mark',
                'items' => [
                    self::item('SVG', 'brand/icon.svg', 'вектор', 64, 85),
                    self::item('Favicon', 'favicon.ico', '16, 32 и 48'),
                    ...self::squares('brand/icon-%d.png', [16, 32, 48, 64, 128, 180, 192, 256, 512, 1024]),
                ],
            ],
            [
                'title' => 'Икона за приложение',
                'text' => 'Същият пин върху бялото от началния екран. За телефона, магазина и иконата на сайта.',
                'kind' => 'plate',
                'items' => self::squares('brand/app-icon-%d.png', [180, 192, 512, 1024]),
            ],
            [
                'title' => 'Лого',
                'text' => 'Пинът заедно с думата Kostaa. Хоризонталното е за редове, а нареденото е като началния екран.',
                'kind' => 'logo',
                'items' => [
                    ...self::heights('Хоризонтално', 'brand/logo-h-%d.png', [64, 128, 256, 512]),
                    ...self::heights('Начален екран', 'brand/logo-stack-%d.png', [128, 256, 512, 1024]),
                ],
            ],
            [
                'title' => 'Банери',
                'text' => 'Готови картини за социалните мрежи. Фонът е бял.',
                'kind' => 'banner',
                'items' => [
                    self::file('Open Graph', 'brand/banner-og.png', '1200×630'),
                    self::file('Facebook корица', 'brand/banner-facebook.png', '820×312'),
                    self::file('X заглавка', 'brand/banner-x.png', '1500×500'),
                    self::file('LinkedIn корица', 'brand/banner-linkedin.png', '1584×396'),
                    self::file('Instagram', 'brand/banner-instagram.png', '1080×1080'),
                    self::file('Instagram история', 'brand/banner-story.png', '1080×1920'),
                    self::file('YouTube', 'brand/banner-youtube.png', '2560×1440'),
                    self::file('Discord', 'brand/banner-discord.png', '1920×1080'),
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $sizes
     * @return list<array{label: string, path: string, size: string, width: ?int, height: ?int}>
     */
    private static function squares(string $pattern, array $sizes): array
    {
        $items = [];

        foreach ($sizes as $size) {
            $items[] = self::file(sprintf('%d×%d', $size, $size), sprintf($pattern, $size), sprintf('%d×%d', $size, $size));
        }

        return $items;
    }

    /**
     * @param  list<int>  $heights
     * @return list<array{label: string, path: string, size: string, width: ?int, height: ?int}>
     */
    private static function heights(string $label, string $pattern, array $heights): array
    {
        $items = [];

        foreach ($heights as $height) {
            $items[] = self::file($label, sprintf($pattern, $height), sprintf('височина %d', $height));
        }

        return $items;
    }

    /**
     * @return array{label: string, path: string, size: string, width: ?int, height: ?int}
     */
    private static function file(string $label, string $path, string $size): array
    {
        $info = is_file(public_path($path)) ? @getimagesize(public_path($path)) : false;

        return self::item($label, $path, $size, $info[0] ?? null, $info[1] ?? null);
    }

    /**
     * @return array{label: string, path: string, size: string, width: ?int, height: ?int}
     */
    private static function item(string $label, string $path, string $size, ?int $width = null, ?int $height = null): array
    {
        return [
            'label' => $label,
            'path' => $path,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }
}
