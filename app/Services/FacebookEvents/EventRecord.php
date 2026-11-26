<?php

namespace App\Services\FacebookEvents;

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

        if (! is_int($id) && ! (is_string($id) && preg_match('/^\d{5,40}$/', $id) === 1)) {
            return null;
        }

        $title = self::plain(is_string($event['name'] ?? null) ? $event['name'] : '');
        $title = mb_substr($title, 0, 80);

        if ($title === '') {
            return null;
        }

        $startsAt = self::moment($event['start_utc'] ?? $event['start_timestamp'] ?? null);

        if ($startsAt === null) {
            return null;
        }

        $description = self::plain(is_string($event['description'] ?? null) ? $event['description'] : '');
        $description = mb_substr($description, 0, 5000);

        if ($description === '' || $description === $title) {
            $description = null;
        }

        $venue = self::plain(is_string($event['location_name'] ?? null) ? $event['location_name'] : '');
        $address = self::plain(is_string($event['address'] ?? null) ? $event['address'] : '');
        $cityLabel = self::plain(is_string($event['city'] ?? null) ? $event['city'] : '');
        $cityId = is_string($event['city_id'] ?? null) || is_int($event['city_id'] ?? null)
            ? (string) $event['city_id']
            : null;
        $image = is_string($event['cover_photo_url'] ?? null) ? $event['cover_photo_url'] : '';

        return new ParsedEvent(
            (string) $id,
            $title,
            $startsAt,
            self::endsAt($event, $startsAt),
            self::pageUrl((string) $id, is_string($event['url'] ?? null) ? $event['url'] : ''),
            $description,
            Client::isCoverUrl($image) ? $image : null,
            $venue !== '' ? $venue : null,
            $address !== '' ? $address : null,
            $cityId !== '' ? $cityId : null,
            $cityLabel !== '' ? $cityLabel : null,
            self::coordinate($event['latitude'] ?? null, 41.2, 44.3),
            self::coordinate($event['longitude'] ?? null, 22.3, 28.7),
            ($event['is_canceled'] ?? false) === true,
            ($event['is_online'] ?? false) === true,
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function endsAt(array $event, Carbon $startsAt): Carbon
    {
        $end = self::moment($event['end_time'] ?? null);

        if ($end === null || ! $end->greaterThan($startsAt)) {
            $minutes = self::durationMinutes($event['duration'] ?? null);
            $end = $minutes !== null
                ? $startsAt->copy()->addMinutes($minutes)
                : $startsAt->copy()->addHours(3);
        }

        return ShowLength::shortenPlaceholder($startsAt, $end);
    }

    private static function moment(mixed $value): ?Carbon
    {
        if (is_int($value) || (is_string($value) && preg_match('/^\d{9,}$/', $value) === 1)) {
            $moment = Carbon::createFromTimestampUTC((int) $value);

            return $moment->year > 2000 ? $moment : null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $moment = Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }

        return $moment->year > 2000 ? $moment : null;
    }

    private static function durationMinutes(mixed $value): ?int
    {
        if (! is_string($value) || ! preg_match('/(\d+)\s*(day|hr|hour|min)/i', $value, $match)) {
            return null;
        }

        $count = max(1, (int) $match[1]);

        return match (strtolower($match[2])) {
            'day' => $count * 24 * 60,
            'min' => $count,
            default => $count * 60,
        };
    }

    private static function coordinate(mixed $value, float $min, float $max): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $number = (float) $value;

        if ($number < $min || $number > $max) {
            return null;
        }

        return $number;
    }

    private static function pageUrl(string $id, string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        if (in_array($host, ['facebook.com', 'www.facebook.com'], true) && str_contains($path, '/events/'.$id)) {
            return 'https://www.facebook.com/events/'.$id.'/';
        }

        return 'https://www.facebook.com/events/'.$id.'/';
    }

    private static function plain(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/(?:(?:\/|\|)\s*)?(?:(?<![\p{L}\p{N}])(?:тел|телефон|phone)\.?\s*[:.]?\s*)?(?:\+?\d[\d\s().-]{6,}\d)/ui', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return trim($value);
    }
}
