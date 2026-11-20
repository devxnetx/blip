<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        $devices = Device::query()
            ->withCount([
                'pins',
                'pins as active_pins_count' => fn ($query) => $query->where('expires_at', '>', now()),
            ])
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->paginate(40);

        return view('admin.devices.index', [
            'devices' => $devices,
        ]);
    }

    public function show(Device $device): View
    {
        $device->loadCount([
            'pins',
            'pins as active_pins_count' => fn ($query) => $query->where('expires_at', '>', now()),
        ]);

        return view('admin.devices.show', [
            'device' => $device,
            'pins' => $device->pins()->latest()->paginate(40),
        ]);
    }

    public function edit(Device $device): View
    {
        return view('admin.devices.edit', [
            'device' => $device,
        ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
        ], [
            'label.max' => 'Името може да е до 40 знака.',
        ]);

        $label = trim((string) ($data['label'] ?? ''));
        $device->forceFill(['label' => $label === '' ? null : $label])->save();

        return redirect()
            ->route('admin.devices.show', $device)
            ->with('status', 'Името е записано.');
    }

    public function ban(Device $device): RedirectResponse
    {
        $device->ban();

        return back()->with('status', 'Устройството е забранено. Активните пинове са скрити.');
    }

    public function unban(Device $device): RedirectResponse
    {
        $device->unban();

        return back()->with('status', 'Устройството пак може да пуска пинове. Скритите пинове остават изтекли.');
    }
}
