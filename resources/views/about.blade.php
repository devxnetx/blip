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

        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: "Schibsted Grotesk", sans-serif;
            font-size: 17px;
            line-height: 1.5;
        }

        .site-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            height: 64px;
            padding: 0 22px;
            border-bottom: 1px solid var(--line);
            background: var(--card);
        }

        .brand {
            color: var(--ink);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.04em;
            text-decoration: none;
        }

        .brand i { color: var(--pin); font-style: normal; }

        .links { display: flex; gap: 6px; }

        .links a {
            padding: 8px 12px;
            border-radius: 8px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .links a.is-on { background: var(--ink); color: var(--card); }

        main {
            width: min(640px, calc(100% - 40px));
            margin: 0 auto;
            padding: 56px 0 80px;
        }

        h1 {
            margin: 0;
            font-family: "Instrument Serif", serif;
            font-size: clamp(32px, 4vw, 44px);
            font-weight: 400;
            letter-spacing: -0.03em;
            line-height: 1.05;
        }

        p { margin: 16px 0 0; color: var(--muted); max-width: 42ch; }

        @media (max-width: 700px) {
            .site-nav { height: auto; padding: 14px 16px; align-items: flex-start; flex-direction: column; gap: 8px; }
            main { width: min(640px, calc(100% - 32px)); padding-top: 36px; }
        }
    </style>
</head>
<body>
    @include('partials.site-nav')
    <main>
        <h1>За нас</h1>
        <p>Kostaa е карта за кратки бележки и събития. Оставяш пин там, където стоиш, или гледаш какво предстои наоколо.</p>
        <p>Пинът стои, докато времето му изтече, и после изчезва. Няма нужда от профил, за да оставиш бележка.</p>
    </main>
</body>
</html>
