<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('partials.social')
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Schibsted+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #e7e4de;
            --ink: #1c1b19;
            --muted: #5e5a54;
            --line: #cfcabe;
            --card: #f6f4ef;
            --pin: #c2410c;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            color: var(--ink);
            font-family: "Schibsted Grotesk", sans-serif;
            font-size: 17px;
            line-height: 1.5;
            background: #d4d1cb;
        }

        .map-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,0.2), rgba(255,255,255,0)),
                repeating-linear-gradient(90deg, transparent, transparent 46px, rgba(28,27,25,0.06) 47px),
                repeating-linear-gradient(0deg, transparent, transparent 46px, rgba(28,27,25,0.06) 47px),
                #d4d1cb;
        }

        .street { position: absolute; background: rgba(255, 255, 255, 0.55); }
        .street.h { left: 0; right: 0; height: 22px; }
        .street.v { top: 0; bottom: 0; width: 16px; }
        .h1 { top: 28%; }
        .h2 { top: 64%; }
        .v1 { left: 24%; }
        .v2 { left: 57%; }

        .rain {
            position: fixed;
            inset: 0;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        a.drop {
            position: absolute;
            top: 0;
            left: 0;
            width: 168px;
            opacity: 0;
            color: inherit;
            text-decoration: none;
            pointer-events: auto;
            cursor: pointer;
            will-change: transform;
        }

        .rain.on a.drop {
            opacity: 1;
            animation-name: fall;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        @keyframes fall {
            from { transform: translate3d(0, -36vh, 0) scale(var(--scale, 1)); }
            to { transform: translate3d(var(--drift, 0px), 120vh, 0) scale(var(--scale, 1)); }
        }

        .bubble {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            background: var(--ink);
            color: #f6f4ef;
            border-radius: 14px;
            padding: 8px;
        }

        .bubble img {
            width: 52px;
            height: 52px;
            flex: none;
            border-radius: 10px;
            object-fit: cover;
            display: block;
            background: #8a847a;
        }

        .bubble .meta { min-width: 0; }

        .bubble strong {
            display: block;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
        }

        .bubble em {
            display: block;
            margin-top: 2px;
            color: #b7b2a8;
            font-size: 12px;
            font-style: normal;
        }

        .bubble:after {
            content: "";
            position: absolute;
            left: 22px;
            bottom: -8px;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 8px solid var(--ink);
        }

        .center {
            position: relative;
            z-index: 5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px 64px;
            text-align: center;
            pointer-events: none;
        }

        .hero-pin {
            position: relative;
            width: min(400px, 100%);
            padding: 22px 22px 20px;
            border-radius: 18px;
            background: var(--card);
            box-shadow: 0 18px 50px rgba(28, 27, 25, 0.22);
            pointer-events: auto;
        }

        .hero-pin:after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -11px;
            margin-left: -11px;
            border-left: 11px solid transparent;
            border-right: 11px solid transparent;
            border-top: 12px solid var(--card);
        }

        h1 {
            margin: 0;
            font-family: "Instrument Serif", serif;
            font-size: clamp(26px, 3vw, 34px);
            font-weight: 400;
            letter-spacing: -0.03em;
            line-height: 1.05;
        }

        .lede {
            margin: 10px auto 0;
            max-width: 36ch;
            color: var(--muted);
            font-size: 14px;
        }

        .stores {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 44px;
            padding: 0 12px 0 10px;
            border: 1px solid #555;
            border-radius: 8px;
            background: #000;
            color: #fff;
            text-decoration: none;
            text-align: left;
        }

        .badge svg { width: 22px; height: 22px; flex: none; }

        .badge small {
            display: block;
            font-size: 8px;
            font-weight: 500;
            letter-spacing: 0.04em;
            line-height: 1;
            text-transform: uppercase;
        }

        .badge b {
            display: block;
            margin-top: 1px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.05;
        }

        .view-map {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 38px;
            margin-top: 10px;
            border-radius: 8px;
            background: var(--pin);
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        #boot {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: opacity 0.45s ease;
        }

        #boot[hidden] { display: none !important; }
        #boot.is-done { opacity: 0; }

        .boot-pin {
            position: relative;
            z-index: 1;
            width: 64px;
            height: 64px;
            margin: 0 auto;
            border-radius: 32px;
            background: var(--pin);
        }

        .boot-pin span {
            position: absolute;
            left: 20px;
            top: 20px;
            width: 24px;
            height: 24px;
            border-radius: 12px;
            background: #fff;
        }

        .boot-pin::after {
            content: "";
            position: absolute;
            left: 20px;
            bottom: -16px;
            width: 24px;
            height: 24px;
            background: var(--pin);
            transform: rotate(45deg);
            z-index: -1;
        }

        .boot-word {
            margin-top: 28px;
            color: var(--ink);
            font-size: 56px;
            font-weight: 700;
            letter-spacing: -0.04em;
            text-align: center;
        }

        .boot-word i { color: var(--pin); font-style: normal; }

        @media (max-width: 700px) {
            body { overflow-x: hidden; }
            a.drop { width: 142px; }
            .center { padding: 56px 14px 48px; }
            h1 { font-size: 26px; }
            .lede { font-size: 13px; }
            .badge { height: 40px; padding-right: 10px; }
            .badge b { font-size: 13px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .rain.on a.drop {
                animation: none;
                opacity: 1;
                top: var(--rest, 12%);
                transform: scale(var(--scale, 1));
            }
            #boot { transition: none; }
        }
    </style>
</head>
<body>
    <div class="map-bg" aria-hidden="true">
        <div class="street h h1"></div>
        <div class="street h h2"></div>
        <div class="street v v1"></div>
        <div class="street v v2"></div>
    </div>
    <div id="rain" class="rain"></div>
    <main class="center">
        <div class="hero-pin">
            <h1>Остави бележка там, където стоиш.</h1>
            <p class="lede">„Пин тук“ ползва местоположението ти. Пишеш кратко съобщение, слагаш снимка ако искаш и избираш колко да стои. Бележката седи над пина на сива карта.</p>
            <div class="stores">
                <a class="badge" href="{{ $appStoreUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M16.4 12.7c0-2.2 1.8-3.2 1.9-3.3-1-1.5-2.6-1.7-3.2-1.7-1.3-.1-2.6.8-3.3.8s-1.7-.8-2.9-.8c-1.5 0-2.8.9-3.6 2.2-1.5 2.7-.4 6.6 1.1 8.8.7 1.1 1.6 2.3 2.7 2.2 1.1 0 1.5-.7 2.8-.7s1.7.7 2.9.7 1.9-1.1 2.6-2.2c.8-1.2 1.2-2.3 1.2-2.4-.1 0-2.2-.9-2.2-3.6zM14.6 6.4c.6-.7 1-1.7.9-2.7-1 .1-2.1.6-2.8 1.4-.6.7-1.1 1.7-.9 2.6 1 .1 2.1-.5 2.8-1.3z"/></svg>
                    <span><small>Download on the</small><b>App Store</b></span>
                </a>
                <a class="badge" href="{{ $playStoreUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#34a853" d="M3.5 20.5 13.2 12 3.5 3.5z"/><path fill="#fbbc04" d="m13.2 12 2.6 2.2-9.4 5.4z"/><path fill="#ea4335" d="M15.8 9.8 13.2 12 3.5 3.5l9.4 5.4z"/><path fill="#4285f4" d="M20.2 10.7c.7.4.7 1.2 0 1.6l-3.4 2-2.6-2.3 2.6-2.3z"/></svg>
                    <span><small>Get it on</small><b>Google Play</b></span>
                </a>
            </div>
            <a class="view-map" href="{{ route('map') }}">Виж картата</a>
        </div>
    </main>
    <div id="boot">
        <div>
            <div class="boot-pin" aria-hidden="true"><span></span></div>
            <div class="boot-word"><i>KO</i>staa</div>
        </div>
    </div>
    <script>
        const categories = @json($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        const rain = document.getElementById('rain');
        const boot = document.getElementById('boot');

        function timeAgo(createdAt) {
            const ms = Date.now() - new Date(createdAt).getTime();
            if (!Number.isFinite(ms) || ms < 45000) return 'Току-що';
            const minutes = Math.floor(ms / 60000);
            if (minutes < 60) return minutes === 1 ? 'преди 1 мин' : 'преди ' + minutes + ' мин';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours === 1 ? 'преди 1 час' : 'преди ' + hours + ' часа';
            const days = Math.floor(hours / 24);
            return days === 1 ? 'преди 1 ден' : 'преди ' + days + ' дни';
        }

        function upcomingLabel(pin) {
            if (!pin || pin.kind !== 'upcoming' || !pin.starts_at) return '';
            if (new Date(pin.starts_at).getTime() <= Date.now()) return '';
            const formatted = new Intl.DateTimeFormat('bg-BG', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit',
            }).format(new Date(pin.starts_at));
            return 'Предстоящо · ' + formatted;
        }

        function meta(pin) {
            return [categories[pin.category] || '', upcomingLabel(pin) || timeAgo(pin.created_at)].filter(Boolean).join(' · ');
        }

        function bubble(pin) {
            const card = document.createElement('div');
            card.className = 'bubble';
            if (pin.image_url) {
                const img = document.createElement('img');
                img.alt = '';
                img.src = pin.image_url;
                card.appendChild(img);
            }
            const info = document.createElement('div');
            info.className = 'meta';
            const title = document.createElement('strong');
            title.textContent = pin.message || '';
            const note = document.createElement('em');
            note.textContent = meta(pin);
            info.append(title, note);
            card.append(info);
            return card;
        }

        function startRain(pins) {
            if (!pins.length) {
                return;
            }
            const total = Math.max(40, pins.length * 3);
            for (let index = 0; index < total; index += 1) {
                const pin = pins[index % pins.length];
                const drop = document.createElement('a');
                const duration = 7 + (index % 8) * 1.35;
                drop.className = 'drop';
                drop.href = '/map?pin=' + encodeURIComponent(pin.id);
                drop.style.left = ((index * 17 + (index % 5) * 9) % 92) + '%';
                drop.style.animationDuration = duration.toFixed(2) + 's';
                drop.style.animationDelay = (-((index * 0.47) % duration)).toFixed(2) + 's';
                drop.style.setProperty('--scale', (0.58 + (index % 6) * 0.08).toFixed(2));
                drop.style.setProperty('--drift', (((index % 7) - 3) * 16) + 'px');
                drop.style.setProperty('--rest', (6 + (index * 13) % 80) + '%');
                drop.appendChild(bubble(pin));
                rain.appendChild(drop);
            }
        }

        async function load() {
            const response = await fetch('/api/pins/latest', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('latest');
            const data = await response.json();
            return Array.isArray(data.pins) ? data.pins.slice(0, 15) : [];
        }

        const opened = performance.now();
        load().then((pins) => {
            const wait = Math.max(0, 1100 - (performance.now() - opened));
            setTimeout(() => {
                startRain(pins);
                rain.classList.add('on');
                boot.classList.add('is-done');
                setTimeout(() => { boot.hidden = true; }, 480);
            }, wait);
        }).catch(() => {
            boot.classList.add('is-done');
            setTimeout(() => { boot.hidden = true; }, 480);
        });
    </script>
</body>
</html>
