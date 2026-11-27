<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(): View
    {
        return view('admin.api-keys.index', [
            'keys' => ApiKey::query()->latest('id')->get(),
            'token' => session('api_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ], [
            'name.required' => 'Напиши име на клиента.',
            'name.max' => 'Името може да е до 80 знака.',
        ]);

        [, $plain] = ApiKey::issue(trim($data['name']));

        return redirect()
            ->route('admin.api-keys')
            ->with('api_token', $plain)
            ->with('status', 'Ключът е създаден. Копирай го сега. Няма да се покаже пак.');
    }

    public function revoke(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->revoke();

        return redirect()
            ->route('admin.api-keys')
            ->with('status', 'Ключът е спрян.');
    }
}
