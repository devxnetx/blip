<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blip — бележка, която изчезва</title>
    <meta name="description" content="Остави кратка бележка там, където стоиш. Добави снимка, ако искаш. Стои на сива карта, докато таймерът изтече.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Schibsted+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
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

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Schibsted Grotesk", sans-serif;
            font-size: 17px;
            line-height: 1.5;
        }

        header, main { width: min(1080px, calc(100% - 40px)); margin: 0 auto; }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 28px 0 8px;
        }

        .mark {
            font-family: "Instrument Serif", serif;
            font-size: 32px;
            letter-spacing: -0.03em;
        }

        .mark span { color: var(--pin); }

        header p { margin: 0; color: var(--muted); font-size: 14px; }

        .hero {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
            padding: 48px 0 28px;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: var(--pin);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-family: "Instrument Serif", serif;
            font-size: clamp(44px, 6vw, 72px);
            font-weight: 400;
            letter-spacing: -0.04em;
            line-height: 0.95;
        }

        .lede { margin: 22px 0 0; max-width: 38ch; color: var(--muted); font-size: 18px; }

        .stores { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }

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

        .phone {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.2), rgba(255,255,255,0)),
                repeating-linear-gradient(90deg, transparent, transparent 46px, rgba(28,27,25,0.06) 47px),
                repeating-linear-gradient(0deg, transparent, transparent 46px, rgba(28,27,25,0.06) 47px),
                #d4d1cb;
            border: 1px solid var(--line);
            border-radius: 28px;
            min-height: 460px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 24px 50px rgba(28, 27, 25, 0.08);
        }

        .street {
            position: absolute;
            background: rgba(255, 255, 255, 0.55);
        }

        .street.h { left: 0; right: 0; height: 18px; top: 38%; }
        .street.v { top: 0; bottom: 0; width: 14px; left: 62%; }

        .bubble {
            position: absolute;
            left: 18%;
            top: 22%;
            width: 168px;
            background: var(--ink);
            color: #f6f4ef;
            border-radius: 14px;
            padding: 10px;
        }

        .bubble img, .thumb {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
            background: #8a847a;
        }

        .bubble strong { display: block; margin-top: 8px; font-size: 14px; font-weight: 600; }
        .bubble em { display: block; margin-top: 2px; color: #b7b2a8; font-size: 12px; font-style: normal; }

        .bubble:after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -8px;
            transform: translateX(-50%);
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 8px solid var(--ink);
        }

        .dot {
            position: absolute;
            left: calc(18% + 78px);
            top: calc(22% + 118px);
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--pin);
            box-shadow: 0 0 0 6px rgba(194, 65, 12, 0.18);
        }

        .here {
            position: absolute;
            right: 18px;
            bottom: 18px;
            background: var(--ink);
            color: white;
            border-radius: 999px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 600;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            padding: 12px 0 72px;
        }

        article {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
        }

        article span {
            display: block;
            color: var(--pin);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        article h2 { margin: 10px 0 8px; font-size: 22px; letter-spacing: -0.03em; }
        article p { margin: 0; color: var(--muted); }

        footer {
            width: min(1080px, calc(100% - 40px));
            margin: 0 auto;
            padding: 0 0 48px;
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 800px) {
            .hero, .steps { grid-template-columns: 1fr; }
            .phone { min-height: 380px; }
            header p { display: none; }
        }
    </style>
</head>
<body>
    <header>
        <div class="mark">Bl<span>i</span>p</div>
        <p>Без акаунт. Пинът изтича.</p>
    </header>
    <main>
        <section class="hero">
            <div>
                <p class="eyebrow">На картата, после изчезва</p>
                <h1>Остави бележка там, където стоиш.</h1>
                <p class="lede">„Пин тук“ ползва местоположението ти. Пишеш кратко съобщение, слагаш снимка ако искаш и избираш колко да стои. Бележката седи над пина на сива карта.</p>
                <div class="stores">
                    <a class="store" href="{{ $appStoreUrl }}" rel="noopener">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M16.4 12.7c0-2.2 1.8-3.2 1.9-3.3-1-1.5-2.6-1.7-3.2-1.7-1.3-.1-2.6.8-3.3.8s-1.7-.8-2.9-.8c-1.5 0-2.8.9-3.6 2.2-1.5 2.7-.4 6.6 1.1 8.8.7 1.1 1.6 2.3 2.7 2.2 1.1 0 1.5-.7 2.8-.7s1.7.7 2.9.7 1.9-1.1 2.6-2.2c.8-1.2 1.2-2.3 1.2-2.4-.1 0-2.2-.9-2.2-3.6zM14.6 6.4c.6-.7 1-1.7.9-2.7-1 .1-2.1.6-2.8 1.4-.6.7-1.1 1.7-.9 2.6 1 .1 2.1-.5 2.8-1.3z"/></svg>
                        <span><small>Изтегли от</small><b>App Store</b></span>
                    </a>
                    <a class="store" href="{{ $playStoreUrl }}" rel="noopener">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#34a853" d="M3.5 20.5 13.2 12 3.5 3.5z"/><path fill="#fbbc04" d="m13.2 12 2.6 2.2-9.4 5.4z"/><path fill="#ea4335" d="M15.8 9.8 13.2 12 3.5 3.5l9.4 5.4z"/><path fill="#4285f4" d="M20.2 10.7c.7.4.7 1.2 0 1.6l-3.4 2-2.6-2.3 2.6-2.3z"/></svg>
                        <span><small>Вземи от</small><b>Google Play</b></span>
                    </a>
                    <a class="view-map" href="{{ route('map') }}">Виж картата</a>
                </div>
            </div>
            <div class="phone" aria-hidden="true">
                <div class="street h"></div>
                <div class="street v"></div>
                <div class="bubble">
                    <div class="thumb"></div>
                    <strong>Изгубени ключове</strong>
                    <em>1 час</em>
                </div>
                <div class="dot"></div>
                <div class="here">Пин тук</div>
            </div>
        </section>
        <section class="steps">
            <article>
                <span>01</span>
                <h2>Пин тук</h2>
                <p>Бутонът е долу вдясно. Пуска пина там, където стоиш, след като позволиш местоположение.</p>
            </article>
            <article>
                <span>02</span>
                <h2>Кратко съобщение</h2>
                <p>До 80 знака. Снимката не е задължителна, а ако я сложиш, излиза като малка картинка над пина.</p>
            </article>
            <article>
                <span>03</span>
                <h2>Таймер</h2>
                <p>Пет минути, един час или 24 часа. Когато времето свърши, пинът изчезва от картата.</p>
            </article>
        </section>
    </main>
    <footer>
        <p>Местоположението се чете, когато натиснеш „Пин тук“. Снимка се качва само ако добавиш. Пинът и снимката се махат, когато таймерът изтече.</p>
    </footer>
</body>
</html>
