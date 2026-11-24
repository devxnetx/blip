@extends('layouts.site')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.css">
    <style>
        .board {
            display: grid;
            grid-template-columns: 200px 460px 420px minmax(380px, 1fr);
            width: 100%;
            height: 100%;
            min-width: 1460px;
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
            background: var(--field);
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

        .pin-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
            color: inherit;
            text-decoration: none;
        }
        .pin-row img {
            width: 72px;
            height: 72px;
            flex: none;
            object-fit: cover;
            border-radius: 12px;
            background: var(--paper);
        }
        .pin-row .copy { min-width: 0; }
        .pin-row strong { display: block; font-size: 15px; line-height: 1.3; }
        .pin-row small { display: block; margin-top: 3px; }

        .pin-open { display: flex; flex-direction: column; overflow: hidden; }
        .pin-head, .pin-foot { flex: none; background: var(--card); }
        .pin-head { padding: 18px 18px 12px; border-bottom: 1px solid var(--line); }
        .pin-head h1 { font-family: "Instrument Serif", serif; font-size: 32px; font-weight: 400; line-height: 1.05; }
        .pin-head .meta { margin: 8px 0 0; color: var(--muted); font-size: 14px; }
        .pin-body { flex: 1; min-height: 0; overflow: auto; padding: 14px 18px 18px; }
        .pin-body img { display: block; width: 100%; border-radius: 14px; background: var(--paper); }
        .pin-body .story { margin: 14px 0 0; white-space: pre-wrap; }
        .pin-foot { padding: 12px 18px 16px; border-top: 1px solid var(--line); }
        .share-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .share-row button {
            margin: 0;
            padding: 8px 14px;
            border: 1.5px solid var(--pin);
            border-radius: 999px;
            background: transparent;
            color: var(--pin);
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            cursor: pointer;
        }
        .share-row button:hover { background: rgba(194, 65, 12, 0.08); }
        .share-status { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .detail { padding: 18px 18px 28px; }
        .detail h1 { font-family: "Instrument Serif", serif; font-size: 32px; font-weight: 400; line-height: 1.05; }
        .open-map { display: inline-flex; margin-top: 16px; color: var(--pin); font-weight: 700; }
        .replies { margin-top: 18px; }
        .replies article { margin-top: 8px; padding: 10px 12px; border-radius: 12px; background: var(--paper); }
        .replies small { color: var(--muted); }

        .map-col { overflow: hidden; border-right: 0; background: var(--line); }
        #pin-map { width: 100%; height: 100%; }

        .blip-icon { background: none; border: none; }
        .bubble-wrap { display: flex; flex-direction: column; align-items: center; width: 240px; }
        .bubble-card { display: flex; flex-direction: column; align-items: center; max-width: 240px; }
        .bubble {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            max-width: 240px;
            background: #1c1b19;
            color: #f6f4ef;
            border-radius: 14px;
            padding: 8px;
            box-shadow: 0 8px 18px rgba(28, 27, 25, 0.28);
        }
        .bubble img {
            width: 72px;
            height: 72px;
            flex: none;
            border-radius: 10px;
            object-fit: cover;
            display: block;
            background: #3a3834;
        }
        .bubble .meta { min-width: 0; flex: 1; }
        .bubble span {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
        }
        .bubble small { display: block; margin-top: 3px; color: #cfcabe; font-size: 11px; font-weight: 600; }
        .tail {
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 8px solid #1c1b19;
        }
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
                        @if ($row['pin']->imageUrl())
                            <img src="{{ $row['pin']->imageUrl() }}" alt="">
                        @endif
                        <span class="copy">
                            <strong>{{ $row['pin']->message }}</strong>
                            <small>{{ collect([\App\Models\Category::label($row['pin']->category), \App\Support\PinPage::distanceLabel($row['meters']), \App\Support\PinPage::whenLabel($row['pin'])])->filter(fn ($part) => $part !== '' && $part !== '—')->implode(' · ') }}</small>
                        </span>
                    </a>
                @empty
                    <p class="quiet" style="padding: 14px;">{{ $category ? 'Няма нищо в тази категория.' : 'Няма нищо в този радиус.' }}</p>
                @endforelse
            @endif
        </section>

        <section class="col {{ $pin ? 'pin-open' : '' }}" aria-label="Пин">
            @if ($pin)
                <header class="pin-head">
                    <h1>{{ $pin->message }}</h1>
                    <p class="meta">{{ collect([\App\Models\Category::label($pin->category), $city['name'] ?? null, \App\Support\PinPage::whenLabel($pin)])->filter(fn ($part) => $part !== '' && $part !== '—')->implode(' · ') }}</p>
                </header>
                <div class="pin-body">
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
                </div>
                <footer class="pin-foot">
                    <div class="share-row">
                        <button id="copy-link" type="button" data-url="{{ url()->current() }}">Копирай връзката към пина</button>
                        <button id="share-link" type="button" data-url="{{ url()->current() }}" data-title="{{ $pin->message }}">Сподели</button>
                    </div>
                    <p class="share-status" id="copy-status" hidden></p>
                </footer>
            @else
                <div class="detail">
                    <h1>{{ $city['name'] ?? 'Пинове' }}</h1>
                    <p class="quiet">{{ $city ? 'Избери пин от списъка.' : 'Избери град, после пин.' }}</p>
                </div>
            @endif
        </section>

        <section class="col map-col" aria-label="Карта">
            <div id="pin-map" data-lat="{{ $map['latitude'] }}" data-lng="{{ $map['longitude'] }}" data-zoom="{{ $map['zoom'] }}" data-marked="{{ $map['marked'] ? '1' : '0' }}"></div>
            @if ($pin)
                <template id="pin-bubble">
                    <div class="blip-icon">
                        <div class="bubble-wrap">
                            <div class="bubble-card">
                                <div class="bubble">
                                    @if ($pin->imageUrl())
                                        <img src="{{ $pin->imageUrl() }}" alt="">
                                    @endif
                                    <div class="meta">
                                        <span>{{ $pin->message }}</span>
                                        @php($bubbleMeta = collect([\App\Models\Category::label($pin->category), \App\Support\PinPage::whenLabel($pin)])->filter(fn ($part) => $part !== '' && $part !== '—'))
                                        @foreach ($bubbleMeta as $part)
                                            <small>{{ $part }}</small>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="tail"></div>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
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

        const copyLink = document.getElementById('copy-link');
        const shareLink = document.getElementById('share-link');
        const copyStatus = document.getElementById('copy-status');

        async function copyText(value) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                    return true;
                }
            } catch (e) {}
            try {
                const area = document.createElement('textarea');
                area.value = value;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.left = '-9999px';
                document.body.append(area);
                area.select();
                const copied = document.execCommand('copy');
                area.remove();
                return copied;
            } catch (e) {
                return false;
            }
        }

        function showCopyStatus(copied, url) {
            if (!copyStatus) return;
            copyStatus.hidden = false;
            copyStatus.textContent = copied ? 'Връзката е копирана.' : url;
        }

        if (copyLink) {
            copyLink.addEventListener('click', async () => {
                showCopyStatus(await copyText(copyLink.dataset.url), copyLink.dataset.url);
            });
        }
        if (shareLink) {
            shareLink.addEventListener('click', async () => {
                const url = shareLink.dataset.url;
                if (navigator.share) {
                    try {
                        await navigator.share({ title: shareLink.dataset.title, text: shareLink.dataset.title, url });
                        return;
                    } catch (error) {
                        if (error && error.name === 'AbortError') return;
                    }
                }
                showCopyStatus(await copyText(url), url);
            });
        }

        const spot = document.getElementById('pin-map');
        const latitude = Number(spot.dataset.lat);
        const longitude = Number(spot.dataset.lng);
        const marked = spot.dataset.marked === '1';
        const pageMap = new maplibregl.Map({
            container: spot,
            style: 'https://tiles.openfreemap.org/styles/liberty',
            center: [longitude, latitude],
            zoom: Number(spot.dataset.zoom || 12),
            pitch: 42,
            bearing: -14,
            padding: marked ? { bottom: 150 } : { bottom: 0 },
            attributionControl: true,
        });
        pageMap.addControl(new maplibregl.NavigationControl({ visualizePitch: false }), 'bottom-right');
        const bubble = document.getElementById('pin-bubble');
        if (marked && bubble) {
            new maplibregl.Marker({ element: bubble.content.firstElementChild.cloneNode(true), anchor: 'bottom' })
                .setLngLat([longitude, latitude])
                .addTo(pageMap);
        }
        pageMap.on('load', () => pageMap.resize());
    </script>
@endpush
