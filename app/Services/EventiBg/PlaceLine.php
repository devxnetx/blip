<?php

namespace App\Services\EventiBg;

class PlaceLine
{
    /**
     * A named hall or street written in the description, separate from the town coordinate.
     *
     * @return array{venue: ?string, address: ?string}|null
     */
    public static function fromDescription(?string $description): ?array
    {
        $line = self::line($description);

        if ($line === null) {
            return null;
        }

        $street = [];
        $rest = [];

        foreach (preg_split('/\s*,\s*/u', $line) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $part = trim((string) preg_replace('/^парковото пространство пред\s+/ui', '', $part));

            if (preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|булевард|пл\.|площад)\s*\S/ui', $part) === 1) {
                $street[] = $part;
                continue;
            }

            $rest[] = $part;
        }

        $city = null;

        if ($rest !== [] && ! self::looksLikeVenue($rest[array_key_last($rest)])) {
            $city = array_pop($rest);
        }

        $venue = $rest;

        $address = array_values(array_filter([...$street, $city]));
        $venueText = trim(implode(', ', $venue));
        $addressText = trim(implode(', ', $address));

        if ($venueText === '' && $addressText === '') {
            return null;
        }

        return [
            'venue' => $venueText !== '' ? $venueText : null,
            'address' => $addressText !== '' ? $addressText : null,
        ];
    }

    private static function line(?string $description): ?string
    {
        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        foreach (preg_split("/\n/u", $description) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^(?:Място|Адрес)\s*:\s*(.+)$/u', $line, $match) === 1) {
                return trim($match[1]);
            }

            if (preg_match('/^📍\s*(.+)$/u', $line, $match) === 1) {
                return trim($match[1]);
            }
        }

        if (preg_match('/парк\s+[„"«]?([^„"»\n,.]{3,60})/ui', $description, $match) === 1) {
            $park = trim($match[1], " \t\"'«».");
            $city = preg_match('/\bСофия\b/u', $description) === 1 ? ', София' : '';

            return 'парк '.$park.$city;
        }

        return null;
    }

    private static function looksLikeVenue(string $part): bool
    {
        return preg_match('/зала|стадион|комплекс|хотел|парк(?!инг)|арена|кино|театър|музей|галерия|ндк|академия|дворец/ui', $part) === 1;
    }
}
