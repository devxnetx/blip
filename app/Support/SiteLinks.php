<?php

namespace App\Support;

use App\Models\Setting;

class SiteLinks
{
    public const DISCORD = 'https://discord.com/channels/1574875809603133562';

    /**
     * @var array<string, string>
     */
    public const SOCIAL = [
        'social_instagram' => 'Instagram',
        'social_facebook' => 'Facebook',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'social_instagram',
            'social_facebook',
            'donate_revolut',
            'donate_eth',
            'donate_sol',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (array_keys(self::SOCIAL) as $key) {
            $rules[$key] = ['nullable', 'string', 'max:255', 'url:http,https'];
        }

        $rules['donate_revolut'] = ['nullable', 'string', 'max:255', 'url:http,https'];
        $rules['donate_eth'] = ['nullable', 'string', 'max:42', 'regex:/^0x[a-fA-F0-9]{40}$/'];
        $rules['donate_sol'] = ['nullable', 'string', 'max:44', 'regex:/^[1-9A-HJ-NP-Za-km-z]{32,44}$/'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $messages = [];

        foreach (self::SOCIAL as $key => $label) {
            $messages[$key.'.url'] = $label.' трябва да е пълна връзка с https://.';
            $messages[$key.'.max'] = 'Връзката е твърде дълга.';
        }

        $messages['donate_revolut.url'] = 'Revolut трябва да е пълна връзка с https://.';
        $messages['donate_revolut.max'] = 'Връзката е твърде дълга.';
        $messages['donate_eth.regex'] = 'Ethereum адресът започва с 0x и е 42 знака.';
        $messages['donate_eth.max'] = 'Ethereum адресът е твърде дълъг.';
        $messages['donate_sol.regex'] = 'Solana адресът не изглежда верен.';
        $messages['donate_sol.max'] = 'Solana адресът е твърде дълъг.';

        return $messages;
    }

    /**
     * @return list<array{id: string, label: string, url: ?string}>
     */
    public static function social(): array
    {
        $rows = [];

        foreach (self::SOCIAL as $key => $label) {
            $rows[] = [
                'id' => substr($key, strlen('social_')),
                'label' => $label,
                'url' => self::httpUrl(Setting::get($key)),
            ];
        }

        $rows[] = [
            'id' => 'discord',
            'label' => 'Discord',
            'url' => self::DISCORD,
        ];

        return $rows;
    }

    public static function revolut(): ?string
    {
        return self::httpUrl(Setting::get('donate_revolut'));
    }

    public static function ethereum(): ?string
    {
        return self::match(Setting::get('donate_eth'), '/^0x[a-fA-F0-9]{40}$/');
    }

    public static function solana(): ?string
    {
        return self::match(Setting::get('donate_sol'), '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/');
    }

    public static function raw(string $key): string
    {
        return Setting::get($key) ?? '';
    }

    private static function httpUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $value;
    }

    private static function match(?string $value, string $pattern): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || preg_match($pattern, $value) !== 1) {
            return null;
        }

        return $value;
    }
}
