<?php

namespace App\Services\SportenKalendar;

use Illuminate\Support\Carbon;

class EventPageParser
{
    public function parse(string $html, string $url): ?ParsedEvent
    {
        $externalId = $this->externalId($url);

        if ($externalId === null || ! preg_match('/<h1[^>]*>\s*([^<]+)/u', $html, $titleMatch)) {
            return null;
        }

        $title = $this->clean($titleMatch[1]);

        if ($title === '') {
            return null;
        }

        $startsAt = $this->moment($html, 'Начало', 'от');
        $endsAt = $this->moment($html, 'Край', 'до');

        if ($startsAt === null) {
            return null;
        }

        if ($endsAt === null || $endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt = $startsAt->copy()->addDay();
        }

        $sportSlug = null;
        $sportName = null;
        $afterTitle = strstr($html, '<h1');

        if (is_string($afterTitle) && preg_match('/href="\/sportove\/([a-z0-9\-]+)"[^>]*>\s*([^<]+?)\s*</u', $afterTitle, $sportMatch)) {
            $sportSlug = $sportMatch[1];
            $sportName = $this->clean($sportMatch[2]);
            if ($sportName === '') {
                $sportName = null;
            }
        }

        $address = null;

        if (preg_match('/Адрес:\s*<\/strong>\s*([^<]+)/u', $html, $addressMatch)) {
            $address = $this->clean($addressMatch[1]);
        }

        if ($address === null || $address === '') {
            if (preg_match('/maps\.google\.com\/\?q=([^"&]+)/', $html, $mapMatch)) {
                $address = $this->clean(html_entity_decode($mapMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $address = trim((string) preg_replace('/\s*България\s*/u', ' ', $address));
                $address = trim($address, " ,");
            }
        }

        if ($address === '') {
            $address = null;
        }

        $imageUrl = null;

        if (preg_match('/property="og:image"\s+content="([^"]+)"/', $html, $imageMatch)) {
            $imageUrl = str_replace(' ', '%20', $imageMatch[1]);
            $imageUrl = preg_replace('#(?<!:)/{2,}#', '/', $imageUrl);
            if (! is_string($imageUrl) || ! str_contains($imageUrl, '/uploads/events/')) {
                $imageUrl = null;
            }
        }

        return new ParsedEvent(
            $externalId,
            mb_substr($title, 0, 80),
            $sportSlug,
            $sportName === null ? null : mb_substr($sportName, 0, 40),
            $startsAt,
            $endsAt,
            $address,
            $imageUrl,
            $url,
            $this->description($html, $title),
        );
    }

    private function externalId(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! preg_match('/-(\d+)$/', $path, $match)) {
            return null;
        }

        return $match[1];
    }

    private function moment(string $html, string $label, string $preposition): ?Carbon
    {
        $pattern = '/'.$label.':\s*<\/strong>\s*(\d{2}-\d{2}-\d{4})\s*'.$preposition.'\s*(\d{2}:\d{2})/u';

        if (! preg_match($pattern, $html, $match)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y H:i', $match[1].' '.$match[2], 'Europe/Sofia')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function description(string $html, string $title): ?string
    {
        if (! preg_match('/single-event-info-text-block"[^>]*>(.*?)<\/div>/su', $html, $match)) {
            return null;
        }

        $text = preg_replace('/<br\s*\/?>/i', "\n", $match[1]) ?? $match[1];
        $text = preg_replace('/<\/p>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $lines = preg_split("/\n/u", $text) ?: [];
        $lines = array_map(fn (string $line): string => trim((string) preg_replace('/[ \t]+/u', ' ', $line)), $lines);
        $text = trim((string) preg_replace("/\n{3,}/u", "\n\n", implode("\n", $lines)));

        if ($text === '' || $text === $title) {
            return null;
        }

        return mb_substr($text, 0, 4000);
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
