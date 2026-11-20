<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[\p{L}\p{N}._-]+$/u', 'unique:users,name'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Напиши име.',
            'name.regex' => 'Името може да е от букви, цифри, точка, тире и долна черта.',
            'name.unique' => 'Това име вече се ползва.',
            'password.required' => 'Напиши парола.',
            'password.min' => 'Паролата трябва да е поне 6 знака.',
            'password.confirmed' => 'Двете пароли не съвпадат.',
        ]);

        $email = Str::lower(Str::slug($data['name'], '.')).'@admins.kostaa';

        if ($email === '@admins.kostaa' || User::query()->where('email', $email)->exists()) {
            return back()->withErrors(['name' => 'Това име вече се ползва.'])->withInput();
        }

        User::query()->create([
            'name' => $data['name'],
            'email' => $email,
            'password' => $data['password'],
        ]);

        return back()->with('status', 'Администраторът е добавен.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['name' => 'Не можеш да изтриеш собствения си вход.']);
        }

        $user->delete();

        return back()->with('status', 'Администраторът е изтрит.');
    }
}
