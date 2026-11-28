<?php

namespace App\Support;

class LatinBulgarian
{
    /**
     * Longer Latin groups are read first, so "ch" stays "ч" and "ts" stays "ц".
     * A final "ia" is "ия" (sofia → софия). "ia" inside a word stays "иа".
     *
     * @var array<string, string>
     */
    private const GROUPS = [
        'sht' => 'щ',
        'iya' => 'ия',
        'zh' => 'ж',
        'ts' => 'ц',
        'ch' => 'ч',
        'sh' => 'ш',
        'yu' => 'ю',
        'ya' => 'я',
    ];

    /** @var array<string, string> */
    private const LETTERS = [
        'a' => 'а',
        'b' => 'б',
        'c' => 'ц',
        'd' => 'д',
        'e' => 'е',
        'f' => 'ф',
        'g' => 'г',
        'h' => 'х',
        'i' => 'и',
        'j' => 'й',
        'k' => 'к',
        'l' => 'л',
        'm' => 'м',
        'n' => 'н',
        'o' => 'о',
        'p' => 'п',
        'q' => 'я',
        'r' => 'р',
        's' => 'с',
        't' => 'т',
        'u' => 'у',
        'v' => 'в',
        'w' => 'в',
        'x' => 'х',
        'y' => 'й',
        'z' => 'з',
    ];

    /**
     * The typed text, plus the Cyrillic spelling when the text uses Latin letters.
     *
     * @return list<string>
     */
    public static function forms(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $cyrillic = self::toCyrillic($text);
        if (mb_strtolower($cyrillic) === mb_strtolower($text)) {
            return [$text];
        }

        return [$text, $cyrillic];
    }

    /**
     * Lowercase Cyrillic, with ъ written as а, so "tarnovo" and "Търново" meet.
     */
    public static function fold(string $text): string
    {
        return strtr(mb_strtolower(self::toCyrillic($text)), [
            'ъ' => 'а',
            'ь' => '',
        ]);
    }

    public static function toCyrillic(string $text): string
    {
        $chars = mb_str_split($text);
        $lower = mb_str_split(mb_strtolower($text));
        $result = '';
        $index = 0;
        $count = count($lower);

        while ($index < $count) {
            $three = implode('', array_slice($lower, $index, 3));
            $two = implode('', array_slice($lower, $index, 2));
            $one = $lower[$index];

            if (isset(self::GROUPS[$three])) {
                $result .= self::GROUPS[$three];
                $index += 3;
                continue;
            }

            if ($two === 'ia' && ! self::isLetter($lower[$index + 2] ?? '')) {
                $result .= 'ия';
                $index += 2;
                continue;
            }

            if (isset(self::GROUPS[$two])) {
                $result .= self::GROUPS[$two];
                $index += 2;
                continue;
            }

            if (isset(self::LETTERS[$one])) {
                $result .= self::LETTERS[$one];
                $index += 1;
                continue;
            }

            $result .= $chars[$index];
            $index += 1;
        }

        return $result;
    }

    private static function isLetter(string $char): bool
    {
        return $char !== '' && preg_match('/\p{L}/u', $char) === 1;
    }
}
