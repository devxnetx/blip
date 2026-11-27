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
        .social-discord { background: #5865F2; }

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
        <symbol id="social-discord" viewBox="0 0 24 24" fill="currentColor"><path d="M19.27 5.33A17.4 17.4 0 0 0 15 4a.1.1 0 0 0-.1.05c-.18.33-.39.76-.53 1.09a16.1 16.1 0 0 0-4.74 0c-.14-.34-.36-.76-.54-1.09A.1.1 0 0 0 9 4a17.4 17.4 0 0 0-4.27 1.33.09.09 0 0 0-.04.03C2.2 9.05 1.5 12.67 1.84 16.24c0 .03.02.06.05.07 1.8 1.32 3.53 2.12 5.24 2.65a.1.1 0 0 0 .11-.04c.4-.55.76-1.13 1.07-1.74a.1.1 0 0 0-.05-.14 11.2 11.2 0 0 1-1.53-.73.1.1 0 0 1-.01-.17c.1-.08.2-.16.3-.24a.1.1 0 0 1 .1-.01c3.2 1.46 6.66 1.46 9.82 0a.1.1 0 0 1 .1.01c.1.08.2.16.3.24a.1.1 0 0 1-.01.17c-.49.28-1 .52-1.53.73a.1.1 0 0 0-.05.14c.32.61.68 1.19 1.07 1.74a.1.1 0 0 0 .11.04c1.72-.53 3.45-1.33 5.24-2.65a.1.1 0 0 0 .05-.07c.41-4.2-.69-7.79-2.9-10.88a.08.08 0 0 0-.04-.03zM8.52 14.1c-.96 0-1.75-.88-1.75-1.96s.77-1.96 1.75-1.96 1.76.88 1.75 1.96c0 1.08-.77 1.96-1.75 1.96zm6.96 0c-.96 0-1.75-.88-1.75-1.96s.78-1.96 1.75-1.96 1.75.88 1.75 1.96c.01 1.08-.78 1.96-1.75 1.96z"/></symbol>
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
