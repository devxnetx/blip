<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Pin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            'kind' => ['nullable', 'in:'.Pin::KIND_NORMAL.','.Pin::KIND_UPCOMING],
            'starts_at' => ['nullable', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:40', Rule::exists('categories', 'slug')],
        ], [
            'message.required' => 'Напиши кратко съобщение.',
            'message.max' => 'Съобщението може да е до 80 знака.',
            'latitude.required' => 'Избери място на картата.',
            'longitude.required' => 'Избери място на картата.',
            'kind.in' => 'Избери сега или предстоящо.',
            'category.exists' => 'Избери една от категориите.',
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

        $kind = $data['kind'] ?? Pin::KIND_NORMAL;
        $startsAt = $kind === Pin::KIND_UPCOMING
            ? Pin::parseStartsAt($data['starts_at'] ?? null)
            : null;

        $pin = Pin::query()->create([
            'device_hash' => $hash,
            'message' => $message,
            'category' => $data['category'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            ...Pin::locatedSchedule($kind, $startsAt, $data['duration']),
        ]);

        return response()->json($pin->toApi(false), 201);
    }

    public function unassigned(): JsonResponse
    {
        $pins = Pin::query()
            ->where('kind', Pin::KIND_UNASSIGNED)
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Pin $pin) => $pin->toApi(false))
            ->values();

        return response()->json(['pins' => $pins]);
    }

    public function storeUnassigned(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:80'],
            'duration' => ['required', 'in:5m,1h,24h'],
            'starts_at' => ['nullable', 'string', 'max:40'],
            'category' => ['required', 'string', 'max:40', Rule::exists('categories', 'slug')],
        ], [
            'message.required' => 'Напиши кратко съобщение.',
            'message.max' => 'Съобщението може да е до 80 знака.',
            'category.exists' => 'Избери една от категориите.',
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

        $pin = Pin::query()->create([
            'device_hash' => $hash,
            'message' => $message,
            'category' => $data['category'],
            ...Pin::unassignedAttributes(Pin::parseStartsAt($data['starts_at'] ?? null), $data['duration']),
        ]);

        return response()->json($pin->toApi(false), 201);
    }

    public function place(Request $request, Pin $pin): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Избери място на картата.',
            'longitude.required' => 'Избери място на картата.',
        ]);

        $admin = $request->user();
        $hash = Device::hashForAdmin($admin->id);
        Device::record($hash, $admin->name);

        if (Pin::hasActivePinNearby($hash, (float) $data['latitude'], (float) $data['longitude'])) {
            throw ValidationException::withMessages([
                'spot' => 'Вече има пин на това място.',
            ]);
        }

        $pin->assignLocation((float) $data['latitude'], (float) $data['longitude']);

        return response()->json($pin->toApi(false));
    }

    public function hide(Pin $pin): RedirectResponse
    {
        if ($pin->expires_at->isFuture()) {
            $pin->forceFill([
                'expires_at' => now(),
                'suppressed_at' => now(),
            ])->save();
        }

        return back()->with('status', 'Пинът е скрит от картата.');
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
