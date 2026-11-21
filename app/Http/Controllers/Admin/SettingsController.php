<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'sporten' => Setting::enabled('import_sporten_enabled'),
            'eventibg' => Setting::enabled('import_eventibg_enabled'),
            'kupibileti' => Setting::enabled('import_kupibileti_enabled'),
            'days' => Setting::daysAhead(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'import_days_ahead' => ['required', 'integer', 'between:1,90'],
        ], [
            'import_days_ahead.required' => 'Напиши колко дни напред.',
            'import_days_ahead.integer' => 'Дните трябва да са цяло число.',
            'import_days_ahead.between' => 'Дните могат да са от 1 до 90.',
        ]);

        Setting::put('import_sporten_enabled', $request->boolean('import_sporten_enabled') ? '1' : '0');
        Setting::put('import_eventibg_enabled', $request->boolean('import_eventibg_enabled') ? '1' : '0');
        Setting::put('import_kupibileti_enabled', $request->boolean('import_kupibileti_enabled') ? '1' : '0');
        Setting::put('import_days_ahead', (string) $data['import_days_ahead']);

        return back()->with('status', 'Настройките са запазени.');
    }
}
