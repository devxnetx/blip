<?php

namespace App\Support;

class ChangelogMarkdown
{
    public static function html(string $markdown): string
    {
        $lines = self::lines($markdown);
        $html = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            $line = $lines[$index];

            if (trim($line) === '') {
                $index++;

                continue;
            }

            if (self::opensFence($line)) {
                $index++;
                $code = [];

                while ($index < $count && ! self::opensFence($lines[$index])) {
                    $code[] = $lines[$index];
                    $index++;
                }

                if ($index < $count) {
                    $index++;
                }

                $html[] = '<pre><code>'.self::escape(implode("\n", $code)).'</code></pre>';

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(\S.*)$/', $line, $heading) === 1) {
                $level = min(4, strlen($heading[1]) + 2);
                $html[] = '<h'.$level.'>'.self::inline($heading[2]).'</h'.$level.'>';
                $index++;

                continue;
            }

            if (str_starts_with($line, '>')) {
                $quote = [];

                while ($index < $count && str_starts_with($lines[$index], '>')) {
                    $quote[] = ltrim(substr($lines[$index], 1));
                    $index++;
                }

                $html[] = '<blockquote><p>'.self::inline(implode(' ', $quote)).'</p></blockquote>';

                continue;
            }

            if (preg_match('/^[-*]\s+\S/', $line) === 1) {
                $html[] = self::listHtml($lines, $index, '/^[-*]\s+/', 'ul');

                continue;
            }

            if (preg_match('/^\d+\.\s+\S/', $line) === 1) {
                $html[] = self::listHtml($lines, $index, '/^\d+\.\s+/', 'ol');

                continue;
            }

            $paragraph = [];

            while ($index < $count && trim($lines[$index]) !== '' && ! self::startsBlock($lines[$index])) {
                $paragraph[] = $lines[$index];
                $index++;
            }

            $html[] = '<p>'.self::inline(implode(' ', $paragraph)).'</p>';
        }

        return implode("\n", $html);
    }

    public static function discord(string $markdown): string
    {
        $lines = self::lines($markdown);
        $out = [];
        $inCode = false;

        foreach ($lines as $line) {
            if (self::opensFence($line)) {
                $inCode = ! $inCode;
                $out[] = '```';

                continue;
            }

            if ($inCode) {
                $out[] = $line;

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(\S.*)$/', $line, $heading) === 1) {
                $out[] = '**'.self::discordInline(trim($heading[2])).'**';

                continue;
            }

            $out[] = self::discordInline($line);
        }

        return trim(implode("\n", $out));
    }

    public static function message(string $title, string $body, string $url): string
    {
        $label = trim(str_replace(["\r", "\n", '[', ']'], ' ', $title));
        $heading = '**['.($label !== '' ? $label : 'Промяна').'](<'.$url.'>)**';
        $notes = self::discord($body);
        $content = $notes === '' ? $heading : $heading."\n".$notes;

        if (mb_strlen($content) <= 1900) {
            return $content;
        }

        $more = '[прочети](<'.$url.'>)';
        $room = 1900 - mb_strlen($heading) - mb_strlen($more) - 2;
        $cut = rtrim(mb_substr($notes, 0, max(0, $room)));

        return $heading."\n".$cut."\n".$more;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function listHtml(array $lines, int &$index, string $marker, string $tag): string
    {
        $items = [];
        $count = count($lines);

        while ($index < $count && preg_match($marker, $lines[$index]) === 1) {
            $items[] = '<li>'.self::inline(preg_replace($marker, '', $lines[$index]) ?? '').'</li>';
            $index++;
        }

        return '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
    }

    private static function inline(string $text): string
    {
        $pattern = '/(`[^`\n]+`)|(\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\))|(\*\*([^*\n]+)\*\*)|(~~([^~\n]+)~~)|(\*([^*\n]+)\*)/u';
        $html = '';
        $offset = 0;

        while (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $whole = $match[0][0];
            $start = $match[0][1];
            $html .= self::escape(substr($text, $offset, $start - $offset));

            if (str_starts_with($whole, '`')) {
                $html .= '<code>'.self::escape(substr($whole, 1, -1)).'</code>';
            } elseif (str_starts_with($whole, '[')) {
                $html .= '<a href="'.self::escape($match[4][0]).'">'.self::escape($match[3][0]).'</a>';
            } elseif (str_starts_with($whole, '**')) {
                $html .= '<strong>'.self::escape($match[6][0]).'</strong>';
            } elseif (str_starts_with($whole, '~~')) {
                $html .= '<s>'.self::escape($match[8][0]).'</s>';
            } else {
                $html .= '<em>'.self::escape($match[10][0]).'</em>';
            }

            $offset = $start + strlen($whole);
        }

        return $html.self::escape(substr($text, $offset));
    }

    private static function discordInline(string $line): string
    {
        $linked = preg_replace_callback(
            '/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/',
            function (array $match): string {
                $label = str_replace(['[', ']'], '', $match[1]);

                return '['.$label.'](<'.$match[2].'>)';
            },
            $line,
        );

        return is_string($linked) ? $linked : $line;
    }

    private static function startsBlock(string $line): bool
    {
        return self::opensFence($line)
            || preg_match('/^(#{1,3})\s+\S/', $line) === 1
            || str_starts_with($line, '>')
            || preg_match('/^[-*]\s+\S/', $line) === 1
            || preg_match('/^\d+\.\s+\S/', $line) === 1;
    }

    private static function opensFence(string $line): bool
    {
        return preg_match('/^```/', trim($line)) === 1;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $markdown): array
    {
        return explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
