<?php

namespace App\Services\EventiBg;

use App\Support\ShowLength;
use Illuminate\Support\Carbon;

class DayParser
{
    /**
     * @return list<ParsedEvent>
     */
    public function parse(string $html): array
    {
        $json = $this->extract($html);

        if ($json === null) {
            return [];
        }

        $rows = json_decode($json, true);

        if (! is_array($rows)) {
            return [];
        }

        $events = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $event = $this->event($row);

            if ($event !== null) {
                $events[$event->slug] = $event;
            }
        }

        return array_values($events);
    }

    /**
     * The calendar date is stored at UTC midnight. The clock is Europe/Sofia.
     *
     * @param  array<string, mixed>  $row
     */
    private function event(array $row): ?ParsedEvent
    {
        $slug = is_string($row['slug'] ?? null) ? $row['slug'] : '';

        if (! preg_match('/^[a-z0-9\-]{1,191}$/', $slug)) {
            return null;
        }

        $title = trim((string) preg_replace('/\s+/u', ' ', (string) ($row['name'] ?? '')));
        $title = mb_substr($title, 0, 80);

        if ($title === '') {
            return null;
        }

        $startsAt = $this->moment($row['start'] ?? null, $row['open_time'] ?? null, false);
        $endsAt = $this->moment($row['end'] ?? null, $row['close_time'] ?? null, true);

        if ($startsAt === null || $endsAt === null) {
            return null;
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt = $endsAt->copy()->addDay();
        }

        $endsAt = ShowLength::shortenPlaceholder($startsAt, $endsAt);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return null;
        }

        $latitude = $this->coordinate($row['lat'] ?? null, -90, 90);
        $longitude = $this->coordinate($row['lng'] ?? null, -180, 180);

        if ($latitude === null || $longitude === null) {
            $latitude = null;
            $longitude = null;
        }

        return new ParsedEvent(
            $slug,
            $title,
            $latitude,
            $longitude,
            $startsAt,
            $endsAt,
            'https://eventibg.com/event/'.$slug,
            $this->description($row['description'] ?? null),
            $this->imageUrl($row['image'] ?? null),
        );
    }

    private function moment(mixed $date, mixed $clock, bool $endOfDay): ?Carbon
    {
        if (! is_string($date) || ! preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $day)) {
            return null;
        }

        $time = '00:00';

        if (is_string($clock) && preg_match('/^(\d{2}:\d{2})/', $clock, $match)) {
            $time = $match[1];
        } elseif ($endOfDay) {
            $time = '23:59';
        }

        try {
            return Carbon::parse($day[1].' '.$time, 'Europe/Sofia')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function description(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $lines = preg_split("/\n/u", $text) ?: [];
        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/[ \t]+/u', ' ', $line)), $lines);
        $text = trim((string) preg_replace("/\n{3,}/u", "\n\n", implode("\n", $lines)));

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 4000);
    }

    private function imageUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path)) {
            $path = $value;
        }

        if (! preg_match('#^/uploads/events/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#', $path)) {
            return null;
        }

        return 'https://eventibg.com'.$path;
    }

    private function coordinate(mixed $value, float $min, float $max): ?float
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

    private function extract(string $html): ?string
    {
        $marker = 'const DAY_EVENTS = ';
        $markerAt = strpos($html, $marker);

        if ($markerAt === false) {
            return null;
        }

        $start = strpos($html, '[', $markerAt);

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($char === '\\') {
                    $escape = true;
                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
