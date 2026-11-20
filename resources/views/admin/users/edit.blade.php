@extends('admin.layout')

@section('title', 'Редакция на '.$user->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Редакция</h1>
            <p class="lede">Празно поле за парола оставя старата.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.users.show', $user) }}">@include('admin.icon', ['name' => 'eye']) Преглед</a>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <label for="name">Име</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="off" required>
            <label for="password">Нова парола</label>
            <input id="password" name="password" type="password" autocomplete="new-password">
            <label for="password_confirmation">Пак новата парола</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            <div class="form-actions">
                <button type="submit">Запази</button>
            </div>
        </form>
    </div>
@endsection
