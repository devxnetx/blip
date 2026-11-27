@extends('layouts.site')

@push('head')
    <style>
        .karta { margin-top: 28px; display: grid; gap: 28px; }
        .karta h2 { margin: 0 0 10px; font-size: 20px; letter-spacing: -0.02em; }
        .karta ul { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px 18px; }
        .karta a { color: var(--pin); font-weight: 700; text-decoration: none; }
        .karta a:hover { text-decoration: underline; }
    </style>
@endpush

@section('content')
    <h1>Карта</h1>
    <p class="lede">Страници, градове и категории. Същият адрес с .xml накрая е файлът за Google.</p>

    <div class="karta">
        @foreach ($groups as $group)
            <section>
                <h2>{{ $group['title'] }}</h2>
                <ul>
                    @foreach ($group['links'] as $link)
                        <li>
                            <a href="{{ str_starts_with($link['path'], '/') ? url($link['path']) : $link['path'] }}" @if (! str_starts_with($link['path'], '/')) target="_blank" rel="noopener noreferrer" @endif>{{ $link['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endsection
