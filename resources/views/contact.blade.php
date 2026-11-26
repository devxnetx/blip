@extends('layouts.site')

@push('head')
    <style>
        main { width: min(1080px, calc(100% - 48px)); }

        .contact-grid {
            display: grid;
            grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
            gap: 22px;
            margin-top: 28px;
            align-items: start;
        }

        .contact-grid .block { margin-top: 0; }

        .contact-side li { color: var(--ink); }

        .social-list {
            list-style: none;
            margin: 16px 0 0;
            padding: 0;
            display: grid;
            gap: 10px;
        }

        .social-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--paper);
            color: inherit;
            text-decoration: none;
        }

        a.social-row:hover { border-color: var(--pin); }

        .social-mark {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: #fff;
            flex: none;
        }

        .social-mark svg { width: 22px; height: 22px; }

        .social-instagram { background: linear-gradient(135deg, #f9ce34, #ee2a7b 55%, #6228d7); }
        .social-facebook { background: #1877f2; }
        .social-telegram { background: #2aabee; }
        .social-tiktok { background: #111; }

        html[data-theme="dark"] .social-tiktok { background: #f6f4ef; color: #111; }

        .social-copy { display: flex; flex-direction: column; min-width: 0; }
        .social-copy strong { font-size: 16px; }
        .social-copy em { color: var(--muted); font-style: normal; font-size: 14px; }

        .check-hint {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 500;
        }

        form.block select {
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--field);
            color: var(--ink);
            font: inherit;
        }

        @media (max-width: 800px) {
            main { width: min(1080px, calc(100% - 28px)); }
            .contact-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@section('content')
    <svg class="nav-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="social-instagram" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></symbol>
        <symbol id="social-facebook" viewBox="0 0 24 24" fill="currentColor"><path d="M14.5 8.5V6.8c0-.7.5-1.1 1.2-1.1H17V3h-2.1C12.4 3 11 4.5 11 6.7v1.8H9v2.8h2V21h3.2v-9.7h2.2l.4-2.8h-2.6z"/></symbol>
        <symbol id="social-telegram" viewBox="0 0 24 24" fill="currentColor"><path d="M21.5 4.3 3.7 11.2c-1.2.5-1.2 1.1-.2 1.4l4.6 1.4 1.8 5.4c.2.6.1.9.7.9.4 0 .6-.2.9-.5l2.5-2.4 4.6 3.4c.8.5 1.4.2 1.6-.8l2.9-13.6c.3-1.2-.4-1.7-1.6-1.3zM9.2 13.6l8.8-5.5c.4-.3.8-.1.5.2l-7.5 6.8-.3 3.2-1.5-4.7z"/></symbol>
        <symbol id="social-tiktok" viewBox="0 0 24 24" fill="currentColor"><path d="M14 3h2.2a5.2 5.2 0 0 0 3.6 3.4v2.3a7.4 7.4 0 0 1-3.6-1v6.6a5.7 5.7 0 1 1-5.7-5.7c.3 0 .6 0 .9.1v2.5a3.2 3.2 0 1 0 2.2 3.1V3z"/></symbol>
    </svg>

    <h1>Контакт</h1>
    <p class="lede">Пиши, ако нещо на картата не е наред, ако пин трябва да се махне, или ако имаш въпрос. Съобщението стига до админ панела.</p>

    @if (session('status'))
        <p class="notice">{{ session('status') }}</p>
    @endif

    <div class="contact-grid">
        <aside class="block contact-side">
            <h2>Социални мрежи</h2>
            <ul class="social-list">
                @foreach ($social as $row)
                    <li>
                        @if ($row['url'])
                            <a class="social-row" href="{{ $row['url'] }}" rel="noopener noreferrer" target="_blank">
                                <span class="social-mark social-{{ $row['id'] }}" aria-hidden="true"><svg><use href="#social-{{ $row['id'] }}"></use></svg></span>
                                <span class="social-copy"><strong>{{ $row['label'] }}</strong></span>
                            </a>
                        @else
                            <div class="social-row">
                                <span class="social-mark social-{{ $row['id'] }}" aria-hidden="true"><svg><use href="#social-{{ $row['id'] }}"></use></svg></span>
                                <span class="social-copy">
                                    <strong>{{ $row['label'] }}</strong>
                                    <em>Още няма връзка.</em>
                                </span>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </aside>

        <form class="block" method="POST" action="{{ route('contact.store') }}">
            @csrf
            <h2>Пиши ни</h2>
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

            <label for="reason">Причина</label>
            <select id="reason" name="reason" required>
                <option value="" @selected(old('reason', '') === '')>Избери</option>
                @foreach ($reasons as $value => $label)
                    <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('reason')
                <p class="field-error">{{ $message }}</p>
            @enderror

            <label for="message">Съобщение</label>
            <textarea id="message" name="message" maxlength="2000" required>{{ old('message') }}</textarea>
            @error('message')
                <p class="field-error">{{ $message }}</p>
            @enderror

            <label for="check">Колко е {{ $check['prompt'] }}?</label>
            <input id="check" name="check" value="{{ old('check') }}" inputmode="numeric" autocomplete="off" maxlength="8" required>
            <input type="hidden" name="check_token" value="{{ $check['token'] }}">
            <p class="check-hint">Кратка сметка, за да спрем автоматичните съобщения.</p>
            @error('check')
                <p class="field-error">{{ $message }}</p>
            @enderror

            <button class="send" type="submit">Изпрати</button>
        </form>
    </div>
@endsection
