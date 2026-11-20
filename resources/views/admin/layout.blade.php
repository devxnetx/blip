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

        .shell { display: flex; min-height: 100vh; }

        .side {
            width: 240px;
            flex: none;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 22px 14px;
            background: var(--card);
            border-right: 1px solid var(--line);
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .mark {
            color: var(--ink);
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.04em;
            text-decoration: none;
            padding: 0 12px;
        }

        .mark i { color: var(--pin); font-style: normal; }

        .side nav { display: flex; flex-direction: column; gap: 4px; }

        .side nav a, .side button, .button {
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: var(--ink);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            text-align: left;
            padding: 10px 12px;
            cursor: pointer;
        }

        .side nav a.on { background: var(--ink); color: var(--card); }

        .side-foot { margin-top: auto; }
        .who { display: block; padding: 0 12px 8px; color: var(--muted); font-weight: 700; }
        .side-foot button { width: 100%; }

        main { flex: 1; min-width: 0; padding: 28px 32px 64px; }

        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }

        h1 { margin: 0; font-size: 32px; letter-spacing: -0.03em; }

        .lede { margin: 8px 0 0; color: var(--muted); max-width: 62ch; }

        .actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            margin-left: auto;
        }

        .actions form { margin: 0; }

        .actions a, .actions button {
            border: 0;
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 14px;
            cursor: pointer;
        }

        .actions .solid, .button.solid { background: var(--ink); color: var(--card); }
        .actions .danger, .button.danger { background: var(--pin); color: #fff; }

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
            vertical-align: middle;
        }

        th { color: var(--muted); font-size: 13px; font-weight: 700; }
        td.actions, th.actions { text-align: right; white-space: nowrap; }

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

        .form-actions { display: flex; justify-content: flex-end; margin-top: 16px; }
        .form-actions button {
            border: 0;
            border-radius: 999px;
            background: var(--ink);
            color: var(--card);
            font: inherit;
            font-weight: 700;
            padding: 10px 16px;
            cursor: pointer;
        }

        .meta { color: var(--muted); margin: 4px 0; }

        code { font-size: 13px; word-break: break-all; }

        .pager {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 16px;
        }

        .pager a, .pager span {
            display: inline-block;
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            font-weight: 700;
            text-decoration: none;
            padding: 8px 14px;
            min-width: 42px;
            text-align: center;
        }

        .pager .on { background: var(--ink); color: var(--card); }
        .pager .off { color: var(--faint); }
        .pager .gap { background: transparent; min-width: 0; padding: 8px 2px; color: var(--muted); }

        .facts { display: flex; gap: 18px; flex-wrap: wrap; margin: 0 0 8px; }
        .facts div { min-width: 120px; }
        .facts b { display: block; font-size: 20px; }
        .facts span { color: var(--muted); font-size: 13px; }

        @media (max-width: 800px) {
            .shell { flex-direction: column; }
            .side { width: auto; height: auto; position: static; border-right: 0; border-bottom: 1px solid var(--line); }
            main { padding: 20px 16px 48px; }
            .page-head { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="side">
            <a class="mark" href="{{ route('admin.devices') }}"><i>KO</i>staa</a>
            <nav>
                <a href="{{ route('admin.devices') }}" @class(['on' => request()->routeIs('admin.devices*')])>Устройства</a>
                <a href="{{ route('admin.users') }}" @class(['on' => request()->routeIs('admin.users*')])>Потребители</a>
                <a href="{{ route('admin.pins') }}" @class(['on' => request()->routeIs('admin.pins*')])>Пинове</a>
                <a href="{{ route('admin.categories') }}" @class(['on' => request()->routeIs('admin.categories*')])>Категории</a>
                <a href="{{ route('map') }}">Карта</a>
            </nav>
            <div class="side-foot">
                <span class="who">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit">Изход</button>
                </form>
            </div>
        </aside>
        <main>
            @if (session('status'))
                <p class="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <p class="error">{{ $errors->first() }}</p>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
