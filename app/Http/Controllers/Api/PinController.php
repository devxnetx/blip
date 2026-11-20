<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Pin;
use App\Models\Reply;
use App\Models\Report;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PinController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bounds = $request->validate([
            'north' => ['required', 'numeric', 'between:-90,90'],
            'south' => ['required', 'numeric', 'between:-90,90'],
            'east' => ['required', 'numeric', 'between:-180,180'],
            'west' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($bounds['north'] < $bounds['south'] || $bounds['east'] < $bounds['west']) {
            throw ValidationException::withMessages([
                'bounds' => 'Границите на картата са невалидни.',
            ]);
        }

        Pin::pruneExpired();

        $deviceHash = $this->optionalDeviceHash($request);

        $pins = Pin::query()
            ->where('expires_at', '>', now())
            ->whereBetween('latitude', [$bounds['south'], $bounds['north']])
            ->whereBetween('longitude', [$bounds['west'], $bounds['east']])
            ->latest()
            ->limit(200)
            ->get();

        $repliedIds = collect();
        $reportedIds = collect();

        if ($deviceHash !== null && $pins->isNotEmpty()) {
            $ids = $pins->pluck('id');
            $repliedIds = Reply::query()
                ->where('device_hash', $deviceHash)
                ->whereIn('pin_id', $ids)
                ->pluck('pin_id');
            $reportedIds = Report::query()
                ->where('device_hash', $deviceHash)
                ->whereIn('pin_id', $ids)
                ->pluck('pin_id');
        }

        $pins = $pins
            ->map(fn (Pin $pin) => $pin->toApi(
                $deviceHash !== null && hash_equals($pin->device_hash, $deviceHash),
                $repliedIds->contains($pin->id),
                $reportedIds->contains($pin->id),
            ))
            ->values();

        return response()->json(['pins' => $pins]);
    }

    public function latest(): JsonResponse
    {
        Pin::pruneExpired();

        $pins = Pin::query()
            ->where('expires_at', '>', now())
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Pin $pin) => $pin->toApi(false))
            ->values();

        return response()->json(['pins' => $pins]);
    }

    public function show(Request $request, Pin $pin): JsonResponse
    {
        $this->ensureActive($pin);

        $hash = $this->optionalDeviceHash($request);
        $mine = is_string($hash) && hash_equals($pin->device_hash, $hash);
        $replied = is_string($hash) && Reply::query()
            ->where('pin_id', $pin->id)
            ->where('device_hash', $hash)
            ->exists();
        $reported = is_string($hash) && Report::query()
            ->where('pin_id', $pin->id)
            ->where('device_hash', $hash)
            ->exists();

        return response()->json([
            'pin' => $pin->toApi($mine, $replied, $reported),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $deviceHash = $this->deviceHash($request);
        $device = Device::record($deviceHash);

        if ($device->isBanned()) {
            throw ValidationException::withMessages([
                'device' => 'Това устройство не може да пуска пинове.',
            ]);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'duration' => ['required', 'in:5m,1h,24h'],
            'category' => ['nullable', 'in:'.implode(',', array_keys(Pin::CATEGORIES))],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'message.required' => 'Напиши кратко съобщение.',
            'message.max' => 'Съобщението може да е до 80 знака.',
            'category.in' => 'Избери една от категориите.',
            'image.uploaded' => 'Снимката не се качи.',
            'image.image' => 'Файлът трябва да е снимка.',
            'image.mimes' => 'Файлът трябва да е снимка.',
            'image.max' => 'Снимката трябва да е до 5 MB.',
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Напиши кратко съобщение.',
            ]);
        }

        if (Pin::hasActivePinNearby($deviceHash, (float) $data['latitude'], (float) $data['longitude'])) {
            throw ValidationException::withMessages([
                'spot' => 'Вече имаш пин на това място.',
            ]);
        }

        $path = $request->hasFile('image')
            ? $request->file('image')->store('pins', 'public')
            : null;

        $pin = Pin::query()->create([
            'device_hash' => $deviceHash,
            'message' => $message,
            'category' => $data['category'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'image_path' => $path,
            'expires_at' => now()->addMinutes(Pin::DURATIONS[$data['duration']]),
        ]);

        return response()->json($pin->toApi(true), 201);
    }

    public function destroy(Request $request, Pin $pin): JsonResponse
    {
        if (! hash_equals($pin->device_hash, $this->deviceHash($request))) {
            abort(404);
        }

        $pin->deleteImage();
        $pin->delete();

        return response()->json(['deleted' => true]);
    }

    public function replies(Request $request, Pin $pin): JsonResponse
    {
        $this->ensureActive($pin);

        $hash = $this->optionalDeviceHash($request);
        $owner = is_string($hash) && hash_equals($pin->device_hash, $hash);
        $replies = $pin->replies()
            ->when(! $owner, function ($query) use ($hash) {
                $query->where(function ($inner) use ($hash) {
                    $inner->where('author_only', false);
                    if (is_string($hash)) {
                        $inner->orWhere('device_hash', $hash);
                    }
                });
            })
            ->orderBy('id')
            ->get()
            ->map(fn (Reply $reply) => $reply->toApi(
                is_string($hash) && hash_equals($reply->device_hash, $hash)
            ))
            ->values();

        return response()->json(['replies' => $replies]);
    }

    public function storeReply(Request $request, Pin $pin): JsonResponse
    {
        $deviceHash = $this->deviceHash($request);
        $this->ensureActive($pin);

        if (hash_equals($pin->device_hash, $deviceHash)) {
            throw ValidationException::withMessages([
                'reply' => 'Не можеш да отговаряш на своя пин.',
            ]);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.Reply::MAX_LENGTH],
            'author_only' => ['sometimes', 'boolean'],
        ], [
            'message.required' => 'Напиши кратък отговор.',
            'message.max' => 'Отговорът може да е до 200 знака.',
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Напиши кратък отговор.',
            ]);
        }

        if (Reply::query()->where('pin_id', $pin->id)->where('device_hash', $deviceHash)->exists()) {
            throw ValidationException::withMessages([
                'reply' => 'Вече си отговорил на този пин.',
            ]);
        }

        try {
            $reply = Reply::query()->create([
                'pin_id' => $pin->id,
                'device_hash' => $deviceHash,
                'message' => $message,
                'author_only' => $request->boolean('author_only'),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'reply' => 'Вече си отговорил на този пин.',
            ]);
        }

        return response()->json($reply->toApi(true), 201);
    }

    public function report(Request $request, Pin $pin): JsonResponse
    {
        $deviceHash = $this->deviceHash($request);
        $this->ensureActive($pin);

        if (hash_equals($pin->device_hash, $deviceHash)) {
            throw ValidationException::withMessages([
                'report' => 'Не можеш да докладваш своя пин.',
            ]);
        }

        $deleted = DB::transaction(function () use ($pin, $deviceHash) {
            try {
                Report::query()->create([
                    'pin_id' => $pin->id,
                    'device_hash' => $deviceHash,
                ]);
            } catch (UniqueConstraintViolationException) {
                // This device already reported the pin. The count stays the same.
            }

            if (Report::query()->where('pin_id', $pin->id)->count() < Report::DELETES_AT) {
                return false;
            }

            $pin->deleteImage();
            $pin->delete();

            return true;
        });

        return response()->json([
            'reported' => true,
            'deleted' => $deleted,
        ]);
    }

    private function deviceHash(Request $request): string
    {
        $id = $request->header('X-Device-Id');

        if (! is_string($id) || ! Str::isUuid($id)) {
            throw ValidationException::withMessages([
                'device' => 'Нужен е идентификатор на устройството.',
            ]);
        }

        return hash('sha256', $id);
    }

    private function ensureActive(Pin $pin): void
    {
        if ($pin->expires_at->lte(now())) {
            abort(404);
        }
    }

    private function optionalDeviceHash(Request $request): ?string
    {
        $id = $request->header('X-Device-Id');

        if (! is_string($id) || ! Str::isUuid($id)) {
            return null;
        }

        return hash('sha256', $id);
    }
}
