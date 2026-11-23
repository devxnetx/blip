@extends('layouts.site')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.css">
    <style>
        .board {
            display: grid;
            grid-template-columns: 240px 320px minmax(300px, 1.15fr) minmax(280px, 1fr);
            height: 100%;
            min-width: 1140px;
        }

        .col {
            min-width: 0;
            min-height: 0;
            overflow: auto;
            background: var(--card);
            border-right: 1px solid var(--line);
        }

        .col h1, .col h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: -0.03em;
        }

        .col-head {
            position: sticky;
            top: 0;
            z-index: 1;
            padding: 14px 14px 10px;
            background: var(--card);
            border-bottom: 1px solid var(--line);
        }

        .col-head p, .quiet { margin: 6px 0 0; color: var(--muted); font-size: 14px; }

        .city-find, .km input[type="range"] { width: 100%; }

        .city-find {
            margin-top: 10px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 14px;
        }

        .choice {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--line);
            color: inherit;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
        }

        .choice small, .pin-row small { color: var(--muted); font-weight: 600; font-size: 13px; }

        .choice.on, .pin-row.on { background: var(--ink); color: var(--card); }
        .choice.on small, .pin-row.on small { color: var(--card); }

        .filters { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }

        .filters a {
            padding: 6px 10px;
            border-radius: 999px;
            background: var(--paper);
            color: var(--ink);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .filters a.on { background: var(--ink); color: var(--card); }

        .km { margin-top: 10px; font-size: 14px; font-weight: 700; }
        .km input { margin-top: 4px; }

        .pin-row { display: block; padding: 12px 14px; border-bottom: 1px solid var(--line); color: inherit; text-decoration: none; }
        .pin-row strong { display: block; font-size: 15px; line-height: 1.3; }
        .pin-row small { display: block; margin-top: 3px; }

        .detail { padding: 18px 18px 28px; }
        .detail h1 { font-family: "Instrument Serif", serif; font-size: clamp(28px, 3vw, 40px); font-weight: 400; line-height: 1.05; }
        .detail .meta { margin-top: 8px; color: var(--muted); font-size: 14px; }
        .detail img { display: block; width: 100%; margin-top: 14px; border-radius: 14px; background: var(--paper); }
        .detail .story { margin: 14px 0 0; white-space: pre-wrap; }
        .detail .open-map { display: inline-flex; margin-top: 16px; color: var(--pin); font-weight: 700; }
        .replies { margin-top: 18px; }
        .replies article { margin-top: 8px; padding: 10px 12px; border-radius: 12px; background: var(--paper); }
        .replies small { color: var(--muted); }

        .map-col { overflow: hidden; border-right: 0; background: #d4d1cb; }
        #pin-map { width: 100%; height: 100%; }
    </style>
    @if ($jsonLd)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT) !!}</script>
    @endif
@endpush

@section('content')
    <div class="board">
        <section class="col" aria-label="Градове">
            <div class="col-head">
                <h2>Градове</h2>
                <input id="city-find" class="city-find" type="search" placeholder="Търси град" autocomplete="off" aria-label="Търси град">
            </div>
            <div id="city-list">
                @foreach ($cities as $row)
                    <a class="choice {{ ($city['slug'] ?? '') === $row['slug'] ? 'on' : '' }}" href="{{ route('pins.city', $row['slug']) }}?{{ http_build_query($query) }}" data-find="{{ $row['find'] }}">
                        <span>{{ $row['name'] }}</span>
                        <small>({{ $row['count'] }})</small>
                    </a>
                @endforeach
            </div>
            <p id="city-empty" class="quiet" hidden style="padding: 12px 14px;">Няма такъв град.</p>
        </section>

        <section class="col" aria-label="Пинове">
            <div class="col-head">
                <h2>{{ $city['name'] ?? 'Пинове' }}</h2>
                @if ($city)
                    <form class="km" method="get" action="{{ $pin ? url()->current() : route('pins.city', $city['slug']) }}">
                        @if ($category)
                            <input type="hidden" name="category" value="{{ $category }}">
                        @endif
                        <label for="km">Радиус <b id="km-label">{{ $km }} км</b></label>
                        <input id="km" name="km" type="range" min="1" max="30" step="1" value="{{ $km }}" aria-label="Радиус в километри">
                    </form>
                    <div class="filters">
                        <a href="{{ ($pin ? url()->current() : route('pins.city', $city['slug'])) }}?{{ http_build_query(['km' => $km]) }}" @class(['on' => $category === null])>Всички</a>
                        @foreach ($listed as $slug => $name)
                            <a href="{{ ($pin ? url()->current() : route('pins.city', $city['slug'])) }}?{{ http_build_query(['km' => $km, 'category' => $slug]) }}" @class(['on' => $category === $slug])>{{ $name }}</a>
                        @endforeach
                    </div>
                @else
                    <p>Избери град.</p>
                @endif
            </div>
            @if ($city)
                @forelse ($pins as $row)
                    <a class="pin-row {{ $pin && $pin->is($row['pin']) ? 'on' : '' }}" href="{{ $row['path'] }}?{{ http_build_query($query) }}">
                        <strong>{{ $row['pin']->message }}</strong>
                        <small>{{ collect([\App\Models\Category::label($row['pin']->category), \App\Support\PinPage::distanceLabel($row['meters']), \App\Support\PinPage::whenLabel($row['pin'])])->filter(fn ($part) => $part !== '' && $part !== '—')->implode(' · ') }}</small>
                    </a>
                @empty
                    <p class="quiet" style="padding: 14px;">{{ $category ? 'Няма нищо в тази категория.' : 'Няма нищо в този радиус.' }}</p>
                @endforelse
            @endif
        </section>

        <section class="col" aria-label="Пин">
            <div class="detail">
                @if ($pin)
                    <h1>{{ $pin->message }}</h1>
                    <p class="meta">{{ collect([\App\Models\Category::label($pin->category), $city['name'] ?? null, \App\Support\PinPage::whenLabel($pin)])->filter(fn ($part) => $part !== '' && $part !== '—')->implode(' · ') }}</p>
                    @if ($pin->imageUrl())
                        <img src="{{ $pin->imageUrl() }}" alt="{{ $pin->message }}">
                    @endif
                    @if (trim((string) $pin->description) !== '')
                        <p class="story">{{ $pin->description }}</p>
                    @endif
                    @if ($replies->isNotEmpty())
                        <div class="replies">
                            <h2>Отговори</h2>
                            @foreach ($replies as $reply)
                                <article>
                                    <p>{{ $reply->message }}</p>
                                </article>
                            @endforeach
                        </div>
                    @endif
                    <a class="open-map" href="{{ route('map', ['pin' => $pin->id]) }}">Отвори в картата</a>
                @else
                    <h1>{{ $city['name'] ?? 'Пинове' }}</h1>
                    <p class="quiet">{{ $city ? 'Избери пин от списъка.' : 'Избери град, после пин.' }}</p>
                @endif
            </div>
        </section>

        <section class="col map-col" aria-label="Карта">
            <div id="pin-map" data-lat="{{ $map['latitude'] }}" data-lng="{{ $map['longitude'] }}" data-zoom="{{ $map['zoom'] }}" data-marked="{{ $map['marked'] ? '1' : '0' }}"></div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.js"></script>
    <script>
        const cityFind = document.getElementById('city-find');
        const cityEmpty = document.getElementById('city-empty');
        cityFind.addEventListener('input', () => {
            const needle = cityFind.value.trim().toLowerCase();
            let shown = 0;
            document.querySelectorAll('#city-list a').forEach((row) => {
                const matches = row.dataset.find.includes(needle);
                row.hidden = !matches;
                if (matches) shown += 1;
            });
            cityEmpty.hidden = shown !== 0;
        });

        const km = document.getElementById('km');
        if (km) {
            const label = document.getElementById('km-label');
            km.addEventListener('input', () => { label.textContent = km.value + ' км'; });
            km.addEventListener('change', () => km.form.submit());
        }

        const spot = document.getElementById('pin-map');
        const latitude = Number(spot.dataset.lat);
        const longitude = Number(spot.dataset.lng);
        const pageMap = new maplibregl.Map({
            container: spot,
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [longitude, latitude],
            zoom: Number(spot.dataset.zoom || 12),
            pitch: 42,
            bearing: -14,
            attributionControl: true,
        });
        pageMap.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'bottom-right');
        if (spot.dataset.marked === '1') {
            new maplibregl.Marker({ color: '#c2410c' }).setLngLat([longitude, latitude]).addTo(pageMap);
        }
        pageMap.on('load', () => pageMap.resize());
    </script>
@endpush
