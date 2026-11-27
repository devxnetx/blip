@extends('layouts.site')

@push('head')
    <style>
        .kit {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .kit-banner { grid-template-columns: 1fr; }

        .piece {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--paper);
            color: inherit;
            text-decoration: none;
        }

        .piece:hover { border-color: var(--pin); }

        .frame {
            display: grid;
            place-items: center;
            min-height: 96px;
            padding: 12px;
            border-radius: 10px;
            overflow: hidden;
        }

        .frame img { display: block; max-width: 100%; height: auto; }

        .frame-mark img, .frame-logo img { width: 72px; }

        .frame-plate { background: #fff; }

        .frame-plate img { width: 72px; }

        .frame-banner { min-height: 0; padding: 0; background: #fff; }

        .frame-banner img { width: 100%; }

        .frame-mark, .frame-logo {
            background-color: #fff;
            background-image:
                linear-gradient(45deg, rgba(28, 27, 25, 0.08) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(28, 27, 25, 0.08) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(28, 27, 25, 0.08) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(28, 27, 25, 0.08) 75%);
            background-size: 16px 16px;
            background-position: 0 0, 0 8px, 8px -8px, -8px 0;
        }

        html[data-theme="dark"] .frame-mark,
        html[data-theme="dark"] .frame-logo {
            background-color: #1c1b19;
            background-image:
                linear-gradient(45deg, rgba(246, 244, 239, 0.08) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(246, 244, 239, 0.08) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(246, 244, 239, 0.08) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(246, 244, 239, 0.08) 75%);
        }

        .piece strong { font-size: 14px; }

        .piece small { color: var(--muted); font-weight: 600; }
    </style>
@endpush

@section('content')
    <h1>Медиа</h1>
    <p class="lede">Официалният знак на Kostaa е оранжевият пин от началния екран. Тук са иконата, логото и банерите, готови за сваляне.</p>

    @foreach ($sections as $section)
        <section class="block">
            <h2>{{ $section['title'] }}</h2>
            <p>{{ $section['text'] }}</p>
            <div class="kit kit-{{ $section['kind'] }}">
                @foreach ($section['items'] as $item)
                    <a class="piece" href="{{ asset($item['path']) }}" download>
                        <span class="frame frame-{{ $section['kind'] }}">
                            <img
                                src="{{ asset($item['path']) }}"
                                alt="{{ $item['label'] }}"
                                @if ($item['width'] && $item['height']) width="{{ $item['width'] }}" height="{{ $item['height'] }}" @endif
                            >
                        </span>
                        <strong>{{ $item['label'] }}</strong>
                        <small>{{ $item['size'] }} · Свали</small>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
