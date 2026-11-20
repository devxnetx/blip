<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Админ') — Kostaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <style>
        :root {
            --paper: #e7e4de;
            --ink: #1c1b19;
            --muted: #5e5a54;
            --faint: #8a847a;
            --card: #f6f4ef;
            --pin: #c2410c;
            --line: #d9d6d0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Schibsted Grotesk", sans-serif;
        }

        a { color: inherit; }

        header {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px 22px;
            background: var(--card);
            border-bottom: 1px solid var(--line);
        }

        .mark {
            color: var(--ink);
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.04em;
            text-decoration: none;
        }

        .mark i { color: var(--pin); font-style: normal; }

        nav { display: flex; gap: 8px; flex-wrap: wrap; }

        nav a, header button, .button {
            border: 0;
            border-radius: 999px;
            background: transparent;
            color: var(--ink);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 12px;
            cursor: pointer;
        }

        nav a.on, .button.solid {
            background: var(--ink);
            color: var(--card);
        }

        .button.danger { background: var(--pin); color: #fff; }

        .card form .button { display: block; margin-top: 16px; }

        .session {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .who { font-weight: 700; }

        main { width: min(1100px, calc(100% - 32px)); margin: 28px auto 64px; }

        h1 { margin: 0 0 8px; font-size: 32px; letter-spacing: -0.03em; }

        .lede { margin: 0 0 22px; color: var(--muted); max-width: 62ch; }

        .card {
            background: var(--card);
            border-radius: 22px;
            padding: 18px;
            margin-bottom: 16px;
        }

        .status, .error {
            border-radius: 14px;
            padding: 12px 14px;
            margin: 0 0 16px;
        }

        .status { background: #e7f0e4; }
        .error { background: #fde8e2; }

        table { width: 100%; border-collapse: collapse; }

        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        th { color: var(--muted); font-size: 13px; font-weight: 700; }

        .wrap { overflow: auto; }

        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 13px;
            font-weight: 700;
            background: #e7f0e4;
        }

        .pill.off { background: #fde8e2; color: var(--pin); }

        label { display: block; margin-top: 12px; font-weight: 700; }

        input {
            width: min(360px, 100%);
            margin-top: 6px;
            border: 0;
            border-radius: 14px;
            background: #fff;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
        }

        .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

        .meta { color: var(--muted); margin: 4px 0; }

        code { font-size: 13px; word-break: break-all; }

        .pager { display: flex; gap: 14px; align-items: center; }

        .facts { display: flex; gap: 18px; flex-wrap: wrap; margin: 0 0 8px; }
        .facts div { min-width: 120px; }
        .facts b { display: block; font-size: 20px; }
        .facts span { color: var(--muted); font-size: 13px; }
    </style>
</head>
<body>
    <header>
        <a class="mark" href="{{ route('admin.devices') }}"><i>KO</i>staa</a>
        <nav>
            <a href="{{ route('admin.devices') }}" @class(['on' => request()->routeIs('admin.devices', 'admin.devices.show')])>Устройства</a>
            <a href="{{ route('admin.users') }}" @class(['on' => request()->routeIs('admin.users')])>Потребители</a>
            <a href="{{ route('admin.pins') }}" @class(['on' => request()->routeIs('admin.pins')])>Пинове</a>
            <a href="{{ route('map') }}">Карта</a>
        </nav>
        <div class="session">
            <span class="who">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">Изход</button>
            </form>
        </div>
    </header>
    <main>
        @if (session('status'))
            <p class="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
