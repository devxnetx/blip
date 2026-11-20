@extends('admin.layout')

@section('title', $user->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $user->name }}</h1>
            <p class="lede">Администратор от {{ $user->created_at?->format('d.m.Y H:i') }}.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.users.edit', $user) }}">Редакция</a>
            @if (! auth()->user()->is($user))
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Изтриваш ли този вход?')">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit">Изтрий</button>
                </form>
            @endif
        </div>
    </div>
@endsection
