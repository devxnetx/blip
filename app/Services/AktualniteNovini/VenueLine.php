<?php

namespace App\Services\AktualniteNovini;

use App\Services\SportenKalendar\NominatimGeocoder;

class VenueLine
{
    /**
     * The club line is one sentence, sometimes split into a name and an address.
     * The phone number is not part of the place.
     *
     * @return array{venue: string, address: string}|null
     */
    public static function place(?string $venue, ?string $address): ?array
    {
        $venue = self::clean($venue);
        $address = self::clean($address);

        if ($venue === '' && $address === '') {
            return null;
        }

        if ($address !== '') {
            $name = str_contains($venue, ',')
                ? (self::fromLine($venue)['venue'] ?? '')
                : $venue;

            return [
                'venue' => $name,
                'address' => self::dropPostalLine($address),
            ];
        }

        return self::fromLine($venue);
    }

    /**
     * @return array{venue: string, address: string}|null
     */
    private static function fromLine(string $line): ?array
    {
        $extra = [];
        $stripped = preg_replace_callback('/\(([^)]*)\)/u', function (array $match) use (&$extra): string {
            foreach (self::chunks($match[1]) as $part) {
                $extra[] = $part;
            }

            return ' ';
        }, $line);
        $parts = array_merge(self::chunks(is_string($stripped) ? $stripped : ''), $extra);
        $streets = [];
        $names = [];
        $plains = [];

        foreach ($parts as $part) {
            $part = self::dropPostal($part);

            if ($part === '') {
                continue;
            }

            if (self::isStreet($part)) {
                $streets[] = $part;
            } elseif (self::isName($part)) {
                $names[] = $part;
            } else {
                $plains[] = $part;
            }
        }

        $cityIndex = self::cityIndex($plains);
        $city = $plains[$cityIndex] ?? '';
        $rest = $plains;

        if ($city !== '') {
            array_splice($rest, $cityIndex, 1);
        }

        $venue = trim(implode(', ', array_filter([...$names, ...$rest])));
        $addressParts = $streets;

        if ($city !== '') {
            $addressParts[] = $city;
        }

        $address = implode(', ', $addressParts);

        if ($venue === '' && $address === '') {
            return null;
        }

        return [
            'venue' => $venue,
            'address' => $address,
        ];
    }

    private static function clean(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/(?:\/|\|)?\s*тел\.?\s*[:.]?\s*[+\d][\d\s\-().]{5,}/ui', ' ', $value) ?? '';
        $value = preg_replace('/\b(?:\+?359|0)\d[\d\s\-().]{6,}\d\b/u', ' ', $value) ?? '';
        $value = NominatimGeocoder::normalize($value);
        $value = preg_replace('/\b(бул\.|ул\.|пл\.)(?:\s+\1)+/ui', '$1', $value) ?? '';

        return trim($value);
    }

    private static function dropPostalLine(string $line): string
    {
        $parts = [];

        foreach (self::chunks($line) as $part) {
            $part = self::dropPostal($part);

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }

    private static function dropPostal(string $part): string
    {
        $part = preg_replace('/\s+1000$/u', '', $part) ?? $part;

        return trim($part);
    }

    /**
     * @return list<string>
     */
    private static function chunks(string $line): array
    {
        $parts = preg_split('/\s*,\s*/u', $line) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): string => trim($part),
            $parts,
        ), fn (string $part): bool => $part !== ''));
    }

    private static function isStreet(string $part): bool
    {
        return preg_match('/(?:^|\s)(?:ул\.|ул(?=\s)|улица|бул\.|бул(?=\s)|булевард|пл\.|пл(?=\s)|площад)\s*\S/ui', $part) === 1;
    }

    private static function isName(string $part): bool
    {
        return preg_match('/зала|стадион|комплекс|хотел|парк(?!инг)|арена|басейн|игрище|дворец|канал|градина|колодрум|езеро|сцена/ui', $part) === 1
            || preg_match('/\b(?:club|bar|pub|hall|night|premium|disco|lounge|arena|hotel|center|centre|resort|complex|garden|stadium|palace)\b|клуб|механа|ресторант|дискотека/ui', $part) === 1;
    }

    /**
     * The town is the first plain part that is written as a place.
     * A Latin brand such as "THE BANK" is not the town.
     *
     * @param  list<string>  $plains
     */
    private static function cityIndex(array $plains): int
    {
        foreach ($plains as $index => $part) {
            if (preg_match('/^(?:с\.|село|гр\.|град)\s+\S/ui', $part) === 1) {
                return $index;
            }
        }

        foreach ($plains as $index => $part) {
            if (preg_match('/^(?:обл\.|област|общ\.|община|ж\.к\.|кв\.|квартал)\b/ui', $part) === 1) {
                continue;
            }

            if (preg_match('/\p{Cyrillic}/u', $part) === 1 && preg_match('/\p{Latin}|\d/u', $part) !== 1) {
                return $index;
            }
        }

        return 0;
    }
}
