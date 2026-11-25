<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use App\Services\Discord\CityNotifier;
use App\Support\ChangelogMarkdown;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(): View
    {
        return view('admin.changelog.index', [
            'entries' => ChangelogEntry::query()
                ->orderByRaw('published_at is null')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.changelog.create', [
            'entry' => new ChangelogEntry,
        ]);
    }

    public function store(Request $request, CityNotifier $discord): RedirectResponse
    {
        $entry = new ChangelogEntry;
        $entry->user_id = $request->user()?->id;
        $this->fill($request, $entry);

        return $this->saved($request, $entry, $discord, true);
    }

    public function edit(ChangelogEntry $entry): View
    {
        return view('admin.changelog.edit', [
            'entry' => $entry,
        ]);
    }

    public function update(Request $request, ChangelogEntry $entry, CityNotifier $discord): RedirectResponse
    {
        $entry->user_id = $request->user()?->id;
        $this->fill($request, $entry);

        return $this->saved($request, $entry, $discord, false);
    }

    public function destroy(ChangelogEntry $entry, CityNotifier $discord): RedirectResponse
    {
        $discord->removeChangelog($entry);
        $entry->delete();

        return redirect()
            ->route('admin.changelog')
            ->with('status', 'Промяната е изтрита.');
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:8000'],
            'id' => ['nullable', 'integer'],
        ]);

        $title = trim((string) ($data['title'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $url = rtrim((string) config('app.url'), '/').'/changelog'.(isset($data['id']) ? '#'.$data['id'] : '');

        return response()->json([
            'html' => ChangelogMarkdown::html($body),
            'discord' => ChangelogMarkdown::message($title, $body, $url),
        ]);
    }

    private function fill(Request $request, ChangelogEntry $entry): void
    {
        $request->merge([
            'title' => trim((string) $request->input('title')),
            'body' => trim((string) $request->input('body')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:8000'],
        ], [
            'title.required' => 'Напиши заглавие.',
            'title.max' => 'Заглавието може да е до 120 знака.',
            'body.required' => 'Напиши промяната.',
            'body.max' => 'Текстът може да е до 8000 знака.',
        ]);

        $entry->title = $data['title'];
        $entry->body = $data['body'];
    }

    private function saved(Request $request, ChangelogEntry $entry, CityNotifier $discord, bool $created): RedirectResponse
    {
        $publish = $request->boolean('publish');
        $wasPublished = $entry->isPublished();

        if ($publish) {
            $entry->published_at ??= now();
            $entry->save();
            $sent = $discord->syncChangelog($entry);

            if (! $sent) {
                $status = 'Промяната е на сайта. Каналът changelog в Discord не я прие.';
            } elseif (! $created && $wasPublished) {
                $status = 'Промяната е обновена в сайта и в Discord.';
            } else {
                $status = 'Промяната е публикувана в сайта и в Discord.';
            }
        } else {
            $discord->removeChangelog($entry);
            $entry->published_at = null;
            $entry->save();
            $status = 'Промяната е записана като чернова.';
        }

        return redirect()
            ->route('admin.changelog')
            ->with('status', $status);
    }
}
