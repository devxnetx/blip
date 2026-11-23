<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(): View
    {
        return view('admin.contacts.index', [
            'messages' => ContactMessage::query()->latest()->paginate(40),
        ]);
    }

    public function show(ContactMessage $contactMessage): View
    {
        if ($contactMessage->read_at === null) {
            $contactMessage->forceFill(['read_at' => now()])->save();
        }

        return view('admin.contacts.show', [
            'contact' => $contactMessage,
        ]);
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return redirect()
            ->route('admin.contacts')
            ->with('status', 'Съобщението е изтрито.');
    }
}
