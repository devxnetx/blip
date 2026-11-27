<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\PartnerEvents;
use App\Support\PinPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerController extends Controller
{
    public function cities(): JsonResponse
    {
        return response()->json(['cities' => PartnerEvents::cities()]);
    }

    public function types(): JsonResponse
    {
        return response()->json(['types' => PartnerEvents::types()]);
    }

    public function times(): JsonResponse
    {
        return response()->json(['times' => PartnerEvents::times()]);
    }

    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', 'max:40'],
            'time' => ['nullable', 'string', Rule::in(array_keys(PartnerEvents::TIMES))],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $city = $data['city'] ?? null;
        $type = $data['type'] ?? null;

        if ($city !== null && PinPage::cityBySlug(PinPage::directory(), $city) === null) {
            throw ValidationException::withMessages([
                'city' => 'Unknown city. Use a slug from /api/v1/cities.',
            ]);
        }

        if ($type !== null && ! array_key_exists($type, Category::options())) {
            throw ValidationException::withMessages([
                'type' => 'Unknown type. Use a slug from /api/v1/types.',
            ]);
        }

        return response()->json(PartnerEvents::events([
            'city' => $city,
            'type' => $type,
            'time' => $data['time'] ?? 'all',
            'page' => (int) ($data['page'] ?? 1),
            'limit' => (int) ($data['limit'] ?? 50),
        ]));
    }
}
