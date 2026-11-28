<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Pin;
use App\Services\Discord\CityNotifier;
use App\Models\PinImport;
use App\Services\SportenKalendar\NominatimGeocoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PinController extends Controller
{
    public function index(Request $request): View
    {
        $kind = $request->string('kind')->toString();

        if (! in_array($kind, [Pin::KIND_NORMAL, Pin::KIND_UNASSIGNED, Pin::KIND_UPCOMING], true)) {
            $kind = Pin::KIND_NORMAL;
        }

        $counts = Pin::query()
            ->selectRaw('kind, count(*) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind');

        return view('admin.pins.index', [
            'pins' => Pin::query()
                ->with('device')
                ->where('kind', $kind)
                ->latest()
                ->paginate(40)
                ->withQueryString(),
            'kind' => $kind,
            'counts' => [
                Pin::KIND_NORMAL => (int) ($counts[Pin::KIND_NORMAL] ?? 0),
                Pin::KIND_UNASSIGNED => (int) ($counts[Pin::KIND_UNASSIGNED] ?? 0),
                Pin::KIND_UPCOMING => (int) ($counts[Pin::KIND_UPCOMING] ?? 0),
            ],
            'stats' => [
                'total' => Pin::query()->count(),
                'active' => Pin::query()->onMap()->count(),
                'unassigned' => Pin::query()
                    ->where('kind', Pin::KIND_UNASSIGNED)
                    ->where('expires_at', '>', now())
                    ->count(),
                'passed' => Pin::query()->where('expires_at', '<=', now())->count(),
            ],
        ]);
    }

    public function imports(): View
    {
        return view('admin.imports.index', [
            'imports' => PinImport::query()->orderByDesc('started_at')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function show(Pin $pin): View
    {
        $pin->load('device');

        return view('admin.pins.show', [
            'pin' => $pin,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'duration' => ['nullable', 'in:5m,1h,24h'],
            'kind' => ['nullable', 'in:'.Pin::KIND_NORMAL.','.Pin::KIND_UPCOMING],
            'starts_at' => ['nullable', 'string', 'max:40'],
            'ends_at' => ['nullable', 'string', 'max:40'],
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
        $endsAt = trim((string) ($data['ends_at'] ?? ''));

        if ($kind === Pin::KIND_UPCOMING && $endsAt !== '') {
            $schedule = Pin::rangedSchedule(
                Pin::parseStartsAt($data['starts_at'] ?? null),
                Pin::parseStartsAt($endsAt, 'ends_at'),
            );
        } else {
            if (($data['duration'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'duration' => 'Избери колко да стои пинът.',
                ]);
            }

            $startsAt = $kind === Pin::KIND_UPCOMING
                ? Pin::parseStartsAt($data['starts_at'] ?? null)
                : null;
            $schedule = Pin::locatedSchedule($kind, $startsAt, $data['duration']);
        }

        $pin = Pin::query()->create([
            'device_hash' => $hash,
            'message' => $message,
            'category' => $data['category'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            ...$schedule,
        ]);

        app(CityNotifier::class)->announcePin($pin);

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

    public function move(Request $request, Pin $pin, NominatimGeocoder $geocoder): JsonResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ], [
            'address.max' => 'Адресът може да е до 180 знака.',
            'latitude.required_with' => 'Избери място на картата.',
            'longitude.required_with' => 'Избери място на картата.',
            'latitude.between' => 'Избери място на картата.',
            'longitude.between' => 'Избери място на картата.',
        ]);

        $address = trim((string) ($data['address'] ?? ''));
        $hasPoint = isset($data['latitude'], $data['longitude'])
            && $data['latitude'] !== null
            && $data['longitude'] !== null;

        if ($address === '' && ! $hasPoint) {
            throw ValidationException::withMessages([
                'address' => 'Напиши адрес или избери място на картата.',
            ]);
        }

        if ($hasPoint) {
            $latitude = (float) $data['latitude'];
            $longitude = (float) $data['longitude'];
        } else {
            $city = $pin->addressCity();
            $address = $this->addressInCity($city['name'], $address);

            try {
                $point = $geocoder->locate($address);
            } catch (\RuntimeException) {
                throw ValidationException::withMessages([
                    'address' => 'Адресът не се намери. Опитай пак след малко.',
                ]);
            }

            if ($point === null || Pin::metersBetween(
                $point['latitude'],
                $point['longitude'],
                $city['latitude'],
                $city['longitude'],
            ) > 30000) {
                throw ValidationException::withMessages([
                    'address' => 'Адресът не се намери.',
                ]);
            }

            $latitude = $point['latitude'];
            $longitude = $point['longitude'];
        }

        $pin->relocate($latitude, $longitude);

        return response()->json($pin->fresh()->toApi(false));
    }

    private function addressInCity(string $city, string $address): string
    {
        if (mb_stripos($address, $city) !== false) {
            return $address;
        }

        return $city.', '.$address;
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

        if ($device->isImporter()) {
            throw ValidationException::withMessages([
                'device' => 'Импортер не се забранява.',
            ]);
        }

        return response()->json([
            'banned' => true,
            'device_id' => $device->id,
            'hidden_pin_ids' => $device->ban(),
        ]);
    }
}
