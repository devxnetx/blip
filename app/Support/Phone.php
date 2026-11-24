<?php

namespace App\Support;

class Phone
{
    public static function is(?string $agent): bool
    {
        $agent = trim((string) $agent);
        if ($agent === '' || self::isCrawler($agent)) {
            return false;
        }

        return (bool) preg_match('/iPhone|iPod|Android.*Mobile|Windows Phone|IEMobile|Opera Mini|webOS|BlackBerry/i', $agent);
    }

    private static function isCrawler(string $agent): bool
    {
        return (bool) preg_match('/googlebot|bingbot|duckduckbot|baiduspider|yandexbot|applebot|slurp|facebookexternalhit|facebot|twitterbot|linkedinbot|discordbot|telegrambot|whatsapp|slackbot|embedly|pinterestbot|petalbot/i', $agent);
    }
}
