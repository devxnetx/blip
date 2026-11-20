<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('listed', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['slug', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->slug,
                'label' => $category->name,
            ])
            ->values();

        return response()->json([
            'categories' => $categories,
        ]);
    }
}
