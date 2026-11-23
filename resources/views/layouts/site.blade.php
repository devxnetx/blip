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
            min-height: 64px;
            padding: 10px 22px;
            border-bottom: 1px solid var(--line);
            background: var(--card);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--ink);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.04em;
            text-decoration: none;
            flex: none;
        }

        .brand img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: block;
        }

        .brand i { color: var(--pin); font-style: normal; }

        .links { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

        .links a {
            padding: 8px 12px;
            border-radius: 999px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .links a.is-on { background: var(--ink); color: var(--card); }

        .links a.map-link {
            background: var(--pin);
            color: #fff;
        }

        .links a.map-link:hover { background: #9a3412; }

        main {
            width: min(760px, calc(100% - 40px));
            margin: 0 auto;
            padding: 48px 0 80px;
        }

        h1 {
            margin: 0;
            font-family: "Instrument Serif", serif;
            font-size: clamp(36px, 5vw, 52px);
            font-weight: 400;
            letter-spacing: -0.03em;
            line-height: 1.05;
        }

        .lede {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 18px;
            max-width: 46ch;
        }

        .block {
            margin-top: 18px;
            padding: 22px 22px 20px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
        }

        .block h2 {
            margin: 0;
            font-size: 20px;
            letter-spacing: -0.02em;
        }

        .block p, .block li { color: var(--muted); }

        .block p { margin: 10px 0 0; }

        .block ul { margin: 10px 0 0; padding-left: 18px; }

        .block li + li { margin-top: 6px; }

        .notice {
            margin: 18px 0 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: #e7f2ea;
            color: #1f7a4d;
            font-weight: 600;
        }

        form.block label {
            display: block;
            margin-top: 14px;
            font-weight: 700;
            font-size: 14px;
        }

        form.block input, form.block textarea {
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            font: inherit;
        }

        form.block textarea { min-height: 140px; resize: vertical; }

        .field-error { margin: 6px 0 0; color: #c5362b; font-size: 14px; }

        button.send, button.copy, a.open {
            display: inline-flex;
            align-items: center;
            margin-top: 16px;
            padding: 12px 18px;
            border: 0;
            border-radius: 999px;
            background: var(--pin);
            color: #fff;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        button.copy, a.open {
            margin-top: 12px;
            background: transparent;
            color: var(--pin);
            border: 1.5px solid var(--pin);
        }

        .socials, .pays { list-style: none; margin: 18px 0 0; padding: 0; display: grid; gap: 12px; }

        .socials li, .pay {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .socials a { color: var(--pin); font-weight: 700; }

        .socials em, .pay em { color: var(--muted); font-style: normal; }

        .pay { display: block; }

        .pay strong { display: block; font-size: 18px; }

        .pay p { margin: 8px 0 0; color: var(--muted); }

        .addr {
            margin: 12px 0 0;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--paper);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 14px;
            line-height: 1.4;
            word-break: break-all;
        }

        .hp {
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        @media (max-width: 800px) {
            .site-nav { align-items: flex-start; padding: 12px 14px; }
            main { width: min(760px, calc(100% - 28px)); padding-top: 32px; }
            .socials li { align-items: flex-start; flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>
    @include('partials.site-nav')
    <main>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
