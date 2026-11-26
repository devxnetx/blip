<?php

namespace App\Support;

class ContactCheck
{
    /**
     * @return array{token: string, prompt: string}
     */
    public static function current(): array
    {
        $stored = session('contact_check');

        if (is_array($stored) && isset($stored['token'], $stored['prompt'], $stored['answer'])) {
            return [
                'token' => (string) $stored['token'],
                'prompt' => (string) $stored['prompt'],
            ];
        }

        return self::issue();
    }

    /**
     * @return array{token: string, prompt: string}
     */
    public static function issue(): array
    {
        $left = random_int(2, 9);
        $right = random_int(2, 9);
        $token = bin2hex(random_bytes(8));

        session([
            'contact_check' => [
                'token' => $token,
                'prompt' => $left.' + '.$right,
                'answer' => (string) ($left + $right),
            ],
        ]);

        return [
            'token' => $token,
            'prompt' => $left.' + '.$right,
        ];
    }

    public static function passes(string $token, string $answer): bool
    {
        $stored = session('contact_check');

        if (! is_array($stored)) {
            return false;
        }

        $expectedToken = (string) ($stored['token'] ?? '');
        $expected = (string) ($stored['answer'] ?? '');

        if ($expectedToken === '' || $expected === '' || ! hash_equals($expectedToken, $token)) {
            return false;
        }

        return hash_equals($expected, trim($answer));
    }

    public static function forget(): void
    {
        session()->forget('contact_check');
    }
}
