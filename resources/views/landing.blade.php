<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kostaa — бележка, която изчезва</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <meta name="description" content="Остави кратка бележка там, където стоиш. Добави снимка, ако искаш. Стои на сива карта, докато таймерът изтече.">
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

        #rain {
            position: fixed;
            inset: 0;
            z-index: 1;
            overflow: hidden;
            pointer-events: none;
        }

        a.drop {
            position: absolute;
            top: 0;
            width: 200px;
            opacity: 0;
            color: inherit;
            text-decoration: none;
            pointer-events: auto;
            cursor: pointer;
        }

        #rain.on a.drop {
            opacity: 1;
            animation-name: fall;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        @keyframes fall {
            from { transform: translate3d(0, -28vh, 0); }
            to { transform: translate3d(10px, 118vh, 0); }
        }

        .bubble {
            position: relative;
            background: var(--ink);
            color: #f6f4ef;
            border-radius: 14px;
            padding: 10px;
        }

        .bubble img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
            background: #8a847a;
        }

        .bubble strong {
            display: block;
            margin-top: 8px;
            font-size: 15px;
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
            z-index: 2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
            text-align: center;
            pointer-events: none;
        }

        .copy {
            width: min(640px, 100%);
            min-width: 0;
            pointer-events: auto;
        }

        h1 {
            margin: 0;
            font-family: "Instrument Serif", serif;
            font-size: clamp(40px, 5vw, 72px);
            font-weight: 400;
            letter-spacing: -0.04em;
            line-height: 0.95;
        }

        .lede {
            margin: 22px auto 0;
            max-width: 38ch;
            color: var(--muted);
            font-size: 18px;
        }

        .stores {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 28px;
        }

        .store {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 168px;
            height: 52px;
            padding: 0 16px 0 12px;
            border-radius: 10px;
            background: #000;
            color: #fff;
            text-decoration: none;
            text-align: left;
        }

        .store svg { width: 22px; height: 22px; flex: none; }

        .store small {
            display: block;
            font-size: 10px;
            line-height: 1.1;
            letter-spacing: 0.02em;
        }

        .store b {
            display: block;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.1;
        }

        .view-map {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 148px;
            height: 52px;
            padding: 0 18px;
            border-radius: 10px;
            background: var(--pin);
            color: #fff;
            text-decoration: none;
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

        @media (max-width: 900px) {
            body { overflow-x: hidden; }
            .center { width: 100%; min-height: auto; padding: 72px 16px 28px; }
            .copy { width: 100%; max-width: 100%; padding: 0; }
            h1 { font-size: 34px; max-width: 9em; }
            .lede { max-width: 28ch; font-size: 16px; }
            .stores { width: 100%; }
            .store, .view-map { min-width: 0; flex: 1 1 140px; }
            .view-map { flex-basis: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            #rain.on a.drop { animation: none; opacity: 1; }
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
    <div id="rain"></div>
    <main class="center">
        <div class="copy">
            <h1>Остави бележка там, където стоиш.</h1>
            <p class="lede">„Пин тук“ ползва местоположението ти. Пишеш кратко съобщение, слагаш снимка ако искаш и избираш колко да стои. Бележката седи над пина на сива карта.</p>
            <div class="stores">
                <a class="store" href="{{ $appStoreUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M16.4 12.7c0-2.2 1.8-3.2 1.9-3.3-1-1.5-2.6-1.7-3.2-1.7-1.3-.1-2.6.8-3.3.8s-1.7-.8-2.9-.8c-1.5 0-2.8.9-3.6 2.2-1.5 2.7-.4 6.6 1.1 8.8.7 1.1 1.6 2.3 2.7 2.2 1.1 0 1.5-.7 2.8-.7s1.7.7 2.9.7 1.9-1.1 2.6-2.2c.8-1.2 1.2-2.3 1.2-2.4-.1 0-2.2-.9-2.2-3.6zM14.6 6.4c.6-.7 1-1.7.9-2.7-1 .1-2.1.6-2.8 1.4-.6.7-1.1 1.7-.9 2.6 1 .1 2.1-.5 2.8-1.3z"/></svg>
                    <span><small>За iPhone</small><b>Expo</b></span>
                </a>
                <a class="store" href="{{ $playStoreUrl }}" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#34a853" d="M3.5 20.5 13.2 12 3.5 3.5z"/><path fill="#fbbc04" d="m13.2 12 2.6 2.2-9.4 5.4z"/><path fill="#ea4335" d="M15.8 9.8 13.2 12 3.5 3.5l9.4 5.4z"/><path fill="#4285f4" d="M20.2 10.7c.7.4.7 1.2 0 1.6l-3.4 2-2.6-2.3 2.6-2.3z"/></svg>
                    <span><small>За Android</small><b>Expo</b></span>
                </a>
                <a class="view-map" href="{{ route('map') }}">Виж картата</a>
            </div>
        </div>
    </main>
    <div id="boot">
        <div>
            <div class="boot-pin" aria-hidden="true"><span></span></div>
            <div class="boot-word"><i>KO</i>staa</div>
        </div>
    </div>
    <script>
        const categories = { seeking: 'Търся', lost: 'Загубено', warning: 'Внимание', free: 'Безплатно', other: 'Друго' };
        const rain = document.getElementById('rain');
        const boot = document.getElementById('boot');
        const lanes = [3, 12, 21, 30, 39, 48, 57, 66, 75, 84, 17, 70];

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

        function meta(pin) {
            return [categories[pin.category] || '', timeAgo(pin.created_at)].filter(Boolean).join(' · ');
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
            const title = document.createElement('strong');
            title.textContent = pin.message || '';
            const note = document.createElement('em');
            note.textContent = meta(pin);
            card.append(title, note);
            return card;
        }

        function startRain(pins) {
            pins.slice(0, lanes.length).forEach((pin, index) => {
                const drop = document.createElement('a');
                drop.className = 'drop';
                drop.href = '/map?pin=' + encodeURIComponent(pin.id);
                drop.style.left = lanes[index] + '%';
                drop.style.animationDuration = (40 + (index % 3) * 6) + 's';
                drop.style.animationDelay = (-index * 5) + 's';
                drop.appendChild(bubble(pin));
                rain.appendChild(drop);
            });
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
