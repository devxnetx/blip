<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = collect(Category::listedOptions())
            ->map(fn (string $name, string $slug): array => [
                'id' => $slug,
                'label' => $name,
            ])
            ->values();

        return response()->json([
            'categories' => $categories,
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
