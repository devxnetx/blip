<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Pin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinController extends Controller
{
    public function index(): View
    {
        return view('admin.pins.index', [
            'pins' => Pin::query()->with('device')->latest()->paginate(40),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'duration' => ['required', 'in:5m,1h,24h'],
            'category' => ['required', 'in:'.implode(',', array_keys(Pin::CATEGORIES))],
        ], [
            'message.required' => 'Напиши кратко съобщение.',
            'message.max' => 'Съобщението може да е до 80 знака.',
            'latitude.required' => 'Избери място на картата.',
            'longitude.required' => 'Избери място на картата.',
            'category.in' => 'Избери една от категориите.',
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Напиши кратко съобщение.',
            ]);
        }

        $admin = $request->user();
        $hash = Device::hashForAdmin($admin->id);
        Device::record($hash, $admin->name);

        if (Pin::hasActivePinNearby($hash, (float) $data['latitude'], (float) $data['longitude'])) {
            throw ValidationException::withMessages([
                'spot' => 'Вече има пин на това място.',
            ]);
        }

        $pin = Pin::query()->create([
            'device_hash' => $hash,
            'message' => $message,
            'category' => $data['category'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'expires_at' => now()->addMinutes(Pin::DURATIONS[$data['duration']]),
        ]);

        return response()->json($pin->toApi(false), 201);
    }

    public function ban(Pin $pin): JsonResponse
    {
        $device = Device::record($pin->device_hash);

        return response()->json([
            'banned' => true,
            'device_id' => $device->id,
            'hidden_pin_ids' => $device->ban(),
        ]);
    }
}
