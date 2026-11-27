@extends('layouts.site')

@section('content')
    <h1>Changelog</h1>
    <p class="lede">Какво се промени в Kostaa.</p>

    @if ($entries->isEmpty())
        <p class="lede">Все още няма публикувани промени.</p>
    @else
        <ol class="releases">
            @foreach ($entries as $entry)
                <li class="release" id="{{ $entry->id }}">
                    <time datetime="{{ $entry->published_at->timezone('Europe/Sofia')->toDateString() }}">{{ $entry->publishedLabel() }}</time>
                    <article>
                        <h2>{{ $entry->title }}</h2>
                        <div class="md">{!! $entry->html() !!}</div>
                    </article>
                </li>
            @endforeach
        </ol>
    @endif
@endsection

@push('head')
    <style>
        main { width: min(920px, calc(100% - 40px)); }

        .releases {
            list-style: none;
            margin: 40px 0 0;
            padding: 0;
        }

        .release {
            display: grid;
            grid-template-columns: 168px minmax(0, 1fr);
            gap: 24px;
        }

        .release time {
            padding-top: 6px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
        }

        .release article {
            position: relative;
            padding: 0 0 36px 28px;
            border-left: 2px solid var(--line);
        }

        .release article::before {
            content: "";
            position: absolute;
            left: -7px;
            top: 8px;
            width: 12px;
            height: 12px;
            border: 2px solid var(--pin);
            border-radius: 50%;
            background: var(--paper);
        }

        .release h2 {
            margin: 0;
            font-family: "Sofia Sans", sans-serif;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.25;
        }

        .md {
            margin-top: 12px;
            font-size: 16px;
            line-height: 1.65;
        }

        .md > :first-child { margin-top: 0; }

        .md p { margin: 0 0 12px; }

        .md h3, .md h4 {
            margin: 22px 0 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--line);
            font-size: 18px;
            letter-spacing: -0.02em;
        }

        .md h4 { font-size: 16px; border-bottom: 0; padding-bottom: 0; }

        .md a { color: var(--pin); font-weight: 600; }

        .md ul, .md ol { margin: 0 0 12px; padding-left: 22px; }

        .md li + li { margin-top: 4px; }

        .md code {
            padding: 1px 6px;
            border-radius: 6px;
            background: var(--card);
            border: 1px solid var(--line);
            font-size: 0.9em;
        }

        .md pre {
            margin: 0 0 12px;
            padding: 14px 16px;
            overflow: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--card);
        }

        .md pre code {
            padding: 0;
            border: 0;
            background: transparent;
        }

        .md blockquote {
            margin: 0 0 12px;
            padding: 0 0 0 14px;
            border-left: 4px solid var(--line);
            color: var(--muted);
        }

        .md blockquote p { margin: 0; }

        @media (max-width: 700px) {
            .release { grid-template-columns: 1fr; gap: 2px; }
            .release time { padding-top: 0; }
            .release article { padding-left: 20px; }
        }
    </style>
@endpush
