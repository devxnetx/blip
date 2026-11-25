<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\SiteLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        $links = [];

        foreach (SiteLinks::keys() as $key) {
            $links[$key] = SiteLinks::raw($key);
        }

        return view('admin.settings.edit', [
            'sporten' => Setting::enabled('import_sporten_enabled'),
            'eventibg' => Setting::enabled('import_eventibg_enabled'),
            'kupibileti' => Setting::enabled('import_kupibileti_enabled'),
            'aktualnitenovini' => Setting::enabled('import_aktualnitenovini_enabled'),
            'facebook' => Setting::enabled('import_facebook_enabled'),
            'days' => Setting::daysAhead(),
            'discordEvery' => Setting::discordEveryHours(),
            'discordAhead' => Setting::discordAheadHours(),
            'links' => $links,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'import_days_ahead' => ['required', 'integer', 'between:1,90'],
            'discord_live_every_hours' => ['required', 'integer', 'between:1,24'],
            'discord_live_ahead_hours' => ['required', 'integer', 'between:1,48'],
            ...SiteLinks::rules(),
        ], [
            'import_days_ahead.required' => 'Напиши колко дни напред.',
            'import_days_ahead.integer' => 'Дните трябва да са цяло число.',
            'import_days_ahead.between' => 'Дните могат да са от 1 до 90.',
            'discord_live_every_hours.required' => 'Напиши на колко часа.',
            'discord_live_every_hours.integer' => 'Часовете трябва да са цяло число.',
            'discord_live_every_hours.between' => 'Интервалът може да е от 1 до 24 часа.',
            'discord_live_ahead_hours.required' => 'Напиши колко часа напред.',
            'discord_live_ahead_hours.integer' => 'Часовете трябва да са цяло число.',
            'discord_live_ahead_hours.between' => 'Прозорецът може да е от 1 до 48 часа.',
            ...SiteLinks::messages(),
        ]);

        Setting::put('import_sporten_enabled', $request->boolean('import_sporten_enabled') ? '1' : '0');
        Setting::put('import_eventibg_enabled', $request->boolean('import_eventibg_enabled') ? '1' : '0');
        Setting::put('import_kupibileti_enabled', $request->boolean('import_kupibileti_enabled') ? '1' : '0');
        Setting::put('import_aktualnitenovini_enabled', $request->boolean('import_aktualnitenovini_enabled') ? '1' : '0');
        Setting::put('import_facebook_enabled', $request->boolean('import_facebook_enabled') ? '1' : '0');
        Setting::put('import_days_ahead', (string) $data['import_days_ahead']);
        Setting::put('discord_live_every_hours', (string) $data['discord_live_every_hours']);
        Setting::put('discord_live_ahead_hours', (string) $data['discord_live_ahead_hours']);

        foreach (SiteLinks::keys() as $key) {
            if (! $request->exists($key)) {
                continue;
            }

            $value = trim((string) $request->input($key, ''));

            if ($value === '') {
                Setting::query()->where('key', $key)->delete();

                continue;
            }

            Setting::put($key, $value);
        }

        return back()->with('status', 'Настройките са запазени.');
    }
}
