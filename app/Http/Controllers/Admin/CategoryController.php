<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount('pins')
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'unique:categories,name'],
            'position' => ['nullable', 'integer', 'between:1,999'],
        ], [
            'name.required' => 'Напиши име.',
            'name.max' => 'Името може да е до 40 знака.',
            'name.unique' => 'Това име вече се ползва.',
            'position.integer' => 'Редът трябва да е число.',
            'position.between' => 'Редът трябва да е между 1 и 999.',
        ]);

        $position = $data['position'] ?? ((int) Category::query()->max('position')) + 1;

        Category::query()->create([
            'slug' => Category::uniqueSlug($data['name']),
            'name' => $data['name'],
            'position' => $position,
        ]);

        return back()->with('status', 'Категорията е добавена.');
    }

    public function show(Category $category): View
    {
        $category->loadCount('pins');

        return view('admin.categories.show', [
            'category' => $category,
            'pins' => $category->pins()->with('device')->latest()->limit(20)->get(),
        ]);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', Rule::unique('categories', 'name')->ignore($category->id)],
            'position' => ['required', 'integer', 'between:1,999'],
        ], [
            'name.required' => 'Напиши име.',
            'name.max' => 'Името може да е до 40 знака.',
            'name.unique' => 'Това име вече се ползва.',
            'position.required' => 'Напиши ред.',
            'position.integer' => 'Редът трябва да е число.',
            'position.between' => 'Редът трябва да е между 1 и 999.',
        ]);

        $category->name = $data['name'];
        $category->position = $data['position'];
        $category->save();

        return redirect()
            ->route('admin.categories.show', $category)
            ->with('status', 'Категорията е записана.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->pins()->exists()) {
            return back()->withErrors([
                'category' => 'Тази категория се ползва от пинове и не може да се изтрие.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories')
            ->with('status', 'Категорията е изтрита.');
    }
}
