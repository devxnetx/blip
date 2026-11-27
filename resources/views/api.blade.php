@extends('layouts.site')

@push('head')
    <style>
        main { width: min(1080px, calc(100% - 48px)); }
        .api-doc { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 28px; margin-top: 22px; align-items: start; }
        .api-nav { position: sticky; top: 16px; display: grid; gap: 8px; }
        .api-nav a { color: var(--ink); font-weight: 700; text-decoration: none; font-size: 14px; }
        .endpoint { margin: 0 0 28px; padding-bottom: 22px; border-bottom: 1px solid var(--line); }
        .verb { display: inline-block; background: #15803d; color: #fff; border-radius: 6px; padding: 3px 8px; font-size: 12px; font-weight: 800; letter-spacing: 0.04em; }
        .endpoint h2 { margin: 10px 0 6px; font-size: 22px; }
        .path { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 15px; }
        .endpoint p { margin: 8px 0; }
        .endpoint table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .endpoint th, .endpoint td { text-align: left; padding: 8px 6px; border-bottom: 1px solid var(--line); font-size: 14px; vertical-align: top; }
        .endpoint code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .endpoint code { font-size: 13px; }
        pre { margin: 8px 0 0; padding: 14px; border-radius: 12px; background: var(--ink); color: var(--card); overflow: auto; font-size: 13px; line-height: 1.45; }
        @media (max-width: 800px) {
            main { width: min(1080px, calc(100% - 28px)); }
            .api-doc { grid-template-columns: 1fr; }
            .api-nav { position: static; }
        }
    </style>
@endpush

@section('content')
    <h1>API</h1>
    <p class="lede">Събития по град, тип и време. Картата и приложението ползват друг адрес. Този е за външни клиенти.</p>
    <p class="notice">Всяка заявка иска заглавие <code>Authorization: Bearer</code> и твоя ключ. Ако ти трябва ключ, <a href="{{ route('contact') }}">пиши ни</a> за цена.</p>

    <div class="api-doc">
        <nav class="api-nav">
            <a href="#events">Събития</a>
            <a href="#cities">Градове</a>
            <a href="#types">Типове</a>
            <a href="#times">Време</a>
        </nav>
        <div>
            <article class="endpoint" id="events">
                <span class="verb">GET</span>
                <h2>Събития</h2>
                <p class="path">{{ $base }}/events</p>
                <p>Връща събития, които още не са изтекли. Бележка без час на започване не влиза. Градът е най-близкият град в радиус 18 км.</p>
                <table>
                    <thead>
                        <tr><th>Параметър</th><th>Описание</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><code>city</code></td><td>Slug от <code>/cities</code>. Празно значи всички градове. Пример: <code>{{ $exampleCity }}</code>.</td></tr>
                        <tr><td><code>type</code></td><td>Slug от <code>/types</code>. Празно значи всички типове. Пример: <code>event</code>.</td></tr>
                        <tr><td><code>time</code></td><td>Едно от <code>now</code>, <code>today</code>, <code>4d</code>, <code>10d</code>, <code>all</code>. Без параметър е <code>all</code>.</td></tr>
                        <tr><td><code>page</code></td><td>Страница, от 1. По подразбиране 1.</td></tr>
                        <tr><td><code>limit</code></td><td>Брой на страница, от 1 до 100. По подразбиране 50.</td></tr>
                    </tbody>
                </table>
                <p><code>time=now</code> са започналите. <code>today</code> застъпва днешния ден по Европа/София. <code>4d</code> и <code>10d</code> взимат събития, които започват до толкова дни, включително вече започналите.</p>
                <pre>curl -H "Authorization: Bearer kst_your_key" "{{ $base }}/events?city={{ $exampleCity }}&amp;type=event&amp;time=10d"</pre>
                <pre>{{ $eventsJson }}</pre>
            </article>

            <article class="endpoint" id="cities">
                <span class="verb">GET</span>
                <h2>Градове</h2>
                <p class="path">{{ $base }}/cities</p>
                <p>Градовете, които можеш да подадеш като <code>city</code>.</p>
                <pre>curl -H "Authorization: Bearer kst_your_key" "{{ $base }}/cities"</pre>
                <pre>{{ $citiesJson }}</pre>
            </article>

            <article class="endpoint" id="types">
                <span class="verb">GET</span>
                <h2>Типове</h2>
                <p class="path">{{ $base }}/types</p>
                <p>Типовете, които можеш да подадеш като <code>type</code>.</p>
                <pre>curl -H "Authorization: Bearer kst_your_key" "{{ $base }}/types"</pre>
                <pre>{{ $typesJson }}</pre>
            </article>

            <article class="endpoint" id="times">
                <span class="verb">GET</span>
                <h2>Време</h2>
                <p class="path">{{ $base }}/times</p>
                <p>Стойностите за <code>time</code> и какво означават.</p>
                <pre>curl -H "Authorization: Bearer kst_your_key" "{{ $base }}/times"</pre>
                <pre>{{ $timesJson }}</pre>
            </article>
        </div>
    </div>
@endsection
