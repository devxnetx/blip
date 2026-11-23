@extends('admin.layout')

@section('title', $contact->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $contact->name }}</h1>
            <p class="lede">
                <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                · {{ $contact->created_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}
            </p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.contacts') }}">@include('admin.icon', ['name' => 'mail']) Съобщения</a>
            <form method="POST" action="{{ route('admin.contacts.destroy', $contact) }}" onsubmit="return confirm('Изтриваш ли това съобщение?')">
                @csrf
                @method('DELETE')
                <button class="danger" type="submit">@include('admin.icon', ['name' => 'trash']) Изтрий</button>
            </form>
        </div>
    </div>

    <div class="card">
        <p class="message-body">{{ $contact->message }}</p>
    </div>
@endsection
