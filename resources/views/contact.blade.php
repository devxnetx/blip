@extends('layouts.site')

@section('content')
    <h1>Контакт</h1>
    <p class="lede">Пиши, ако нещо на картата не е наред, ако пин трябва да се махне, или ако имаш въпрос. Съобщението стига до админ панела.</p>

    @if (session('status'))
        <p class="notice">{{ session('status') }}</p>
    @endif

    <ul class="socials">
        @foreach ($social as $row)
            <li>
                @if ($row['url'])
                    <a href="{{ $row['url'] }}" rel="noopener noreferrer" target="_blank">{{ $row['label'] }}</a>
                @else
                    <span>{{ $row['label'] }}</span>
                    <em>Още няма връзка.</em>
                @endif
            </li>
        @endforeach
    </ul>

    <form class="block" method="POST" action="{{ route('contact.store') }}">
        @csrf
        <div class="hp" aria-hidden="true">
            <label for="company">Компания</label>
            <input id="company" name="company" tabindex="-1" autocomplete="off">
        </div>

        <label for="name">Име</label>
        <input id="name" name="name" value="{{ old('name') }}" maxlength="80" autocomplete="name" required>
        @error('name')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <label for="email">Имейл</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="160" autocomplete="email" required>
        @error('email')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <label for="message">Съобщение</label>
        <textarea id="message" name="message" maxlength="2000" required>{{ old('message') }}</textarea>
        @error('message')
            <p class="field-error">{{ $message }}</p>
        @enderror

        <button class="send" type="submit">Изпрати</button>
    </form>
@endsection
