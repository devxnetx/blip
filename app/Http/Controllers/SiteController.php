<?php

namespace App\Http\Controllers;

use App\Models\ChangelogEntry;
use App\Models\ContactMessage;
use App\Support\ContactCheck;
use App\Support\LinkPreview;
use App\Support\PinPage;
use App\Support\SiteLinks;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function changelog(): View
    {
        return view('changelog', [
            'entries' => ChangelogEntry::query()
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(),
            'preview' => $this->preview(
                'Changelog — Kostaa',
                'Какво се промени в Kostaa.',
                '/changelog',
            ),
        ]);
    }

    public function how(): View
    {
        return view('how', [
            'preview' => $this->preview(
                'Как работи — Kostaa',
                'Пин се пуска само там, където стоиш. Предстоящо събитие се вижда веднага и чака часа си.',
                '/how',
            ),
        ]);
    }

    public function contact(): View
    {
        return view('contact', [
            'social' => SiteLinks::social(),
            'reasons' => ContactMessage::REASONS,
            'check' => ContactCheck::current(),
            'preview' => $this->preview(
                'Контакт — Kostaa',
                'Пиши ни за картата. Съобщението стига до админ панела.',
                '/contact',
            ),
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        if (trim((string) $request->input('company')) !== '') {
            return redirect()
                ->route('contact')
                ->with('status', 'Съобщението е изпратено. Ще го прочетем.');
        }

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => trim((string) $request->input('email')),
            'message' => trim((string) $request->input('message')),
            'check' => trim((string) $request->input('check')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:160'],
            'reason' => ['required', 'string', Rule::in(array_keys(ContactMessage::REASONS))],
            'message' => ['required', 'string', 'max:2000'],
            'check' => ['required', 'string', 'max:8'],
            'check_token' => ['required', 'string', 'max:40'],
        ], [
            'name.required' => 'Напиши име.',
            'name.max' => 'Името може да е до 80 знака.',
            'email.required' => 'Напиши имейл.',
            'email.email' => 'Имейлът не изглежда верен.',
            'email.max' => 'Имейлът е твърде дълъг.',
            'reason.required' => 'Избери причина.',
            'reason.in' => 'Избери причина.',
            'message.required' => 'Напиши съобщение.',
            'message.max' => 'Съобщението може да е до 2000 знака.',
            'check.required' => 'Реши задачата.',
            'check_token.required' => 'Зареди страницата отново и реши задачата.',
        ]);

        if (! ContactCheck::passes($data['check_token'], $data['check'])) {
            return redirect()
                ->route('contact')
                ->withInput()
                ->withErrors(['check' => 'Отговорът не е верен.']);
        }

        ContactCheck::forget();

        ContactMessage::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'reason' => $data['reason'],
            'message' => $data['message'],
        ]);

        return redirect()
            ->route('contact')
            ->with('status', 'Съобщението е изпратено. Ще го прочетем.');
    }

    public function karta(): View
    {
        return view('karta', [
            'groups' => PinPage::guide(),
            'preview' => $this->preview(
                'Карта — Kostaa',
                'Страници, градове и категории.',
                '/sitemap',
            ),
        ]);
    }

    public function history(): View
    {
        return view('history', [
            'preview' => $this->preview(
                'История — Kostaa',
                'Миналите събития ще са тук.',
                '/history',
            ),
        ]);
    }

    public function support(): View
    {
        return view('support', [
            'revolut' => SiteLinks::revolut(),
            'ethereum' => SiteLinks::ethereum(),
            'solana' => SiteLinks::solana(),
            'preview' => $this->preview(
                'Подкрепи ни — Kostaa',
                'Kostaa е безплатна. Ако искаш, можеш да помогнеш с Revolut, Ethereum или Solana.',
                '/support',
            ),
        ]);
    }

    /**
     * @return array{title: string, description: string, image: string, image_type: string, url: string}
     */
    private function preview(string $title, string $description, string $path): array
    {
        return LinkPreview::page(
            $title,
            $description,
            rtrim(request()->getSchemeAndHttpHost(), '/').$path,
        );
    }
}
