<?php

namespace App\Services\AktualniteNovini;

use App\Support\ShowLength;
use Illuminate\Support\Carbon;

class EventRecord
{
    /**
     * @param  array<string, mixed>  $event
     */
    public static function from(array $event): ?ParsedEvent
    {
        $id = $event['id'] ?? null;

        if (! is_int($id) && ! (is_string($id) && preg_match('/^\d+$/', $id) === 1)) {
            return null;
        }

        $title = self::plain(is_string($event['title'] ?? null) ? $event['title'] : '');
        $title = mb_substr($title, 0, 80);

        if ($title === '') {
            return null;
        }

        $startsAt = self::moment($event['utc_start_date'] ?? null);
        $endsAt = self::moment($event['utc_end_date'] ?? null);

        if ($startsAt === null) {
            return null;
        }

        if ($endsAt === null || $endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt = ShowLength::startsAtSofiaMidnight($startsAt)
                ? $startsAt->copy()->addHours(3)
                : $startsAt->copy()->addDay()->subSecond();
        }

        $endsAt = ShowLength::shortenPlaceholder($startsAt, $endsAt);

        $venue = is_array($event['venue'] ?? null) ? $event['venue'] : [];
        $place = VenueLine::place(
            is_string($venue['venue'] ?? null) ? $venue['venue'] : null,
            is_string($venue['address'] ?? null) ? $venue['address'] : null,
        );
        $description = self::plain(is_string($event['description'] ?? null) ? $event['description'] : '');
        $description = mb_substr($description, 0, 5000);

        return new ParsedEvent(
            (string) $id,
            $title,
            $startsAt,
            $endsAt,
            self::pageUrl($event),
            $description !== '' ? $description : null,
            self::imageUrl($event),
            $place['venue'] ?? null,
            ($place['address'] ?? '') !== '' ? $place['address'] : null,
        );
    }

    private static function moment(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $moment = Carbon::parse($value, 'UTC')->utc();

        return $moment->year > 1970 ? $moment : null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function pageUrl(array $event): string
    {
        $url = is_string($event['url'] ?? null) ? $event['url'] : '';
        $host = parse_url($url, PHP_URL_HOST);

        if (in_array($host, ['aktualnitenovini.com', 'www.aktualnitenovini.com'], true)) {
            return $url;
        }

        $slug = is_string($event['slug'] ?? null) ? trim($event['slug'], '/') : '';

        if ($slug !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $slug) === 1) {
            return 'https://aktualnitenovini.com/events/'.$slug.'/';
        }

        return 'https://aktualnitenovini.com/uchastiya/';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function imageUrl(array $event): ?string
    {
        $image = $event['image'] ?? null;
        $url = is_array($image) && is_string($image['url'] ?? null) ? $image['url'] : '';

        if ($url === '' || ! Client::isImageUrl($url)) {
            return null;
        }

        return $url;
    }

    private static function plain(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return trim($value);
    }
}
