<!DOCTYPE html>
<html lang="bg">
<head>
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('kostaa-theme');
                var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (dark) document.documentElement.setAttribute('data-theme', 'dark');
            } catch (e) {}
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Админ') — Kostaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sofia+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
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
            --field: #fff;
            color-scheme: light;
        }

        html[data-theme="dark"] {
            --paper: #12110f;
            --ink: #f6f4ef;
            --muted: #cfcabe;
            --faint: #8a847b;
            --card: #1c1b19;
            --pin: #e85d2a;
            --line: #3a3834;
            --field: #2a2825;
            color-scheme: dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: "Sofia Sans", sans-serif;
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

        .brand-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-right: 4px;
        }

        .mark {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
            color: var(--ink);
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.04em;
            text-decoration: none;
            padding: 0 0 0 12px;
        }

        .theme-switch {
            display: inline-flex;
            align-items: center;
            flex: none;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            cursor: pointer;
        }

        .theme-switch i {
            position: relative;
            display: block;
            width: 36px;
            height: 22px;
            border-radius: 999px;
            background: var(--line);
        }

        .theme-switch i::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--card);
        }

        html[data-theme="dark"] .theme-switch i { background: var(--pin); }
        html[data-theme="dark"] .theme-switch i::after { transform: translateX(14px); background: #f6f4ef; }

        .mark img { width: 28px; height: 28px; border-radius: 8px; display: block; }

        .mark i { color: var(--pin); font-style: normal; }

        .side nav { display: flex; flex-direction: column; gap: 4px; }

        .nav-label {
            margin: 16px 12px 2px;
            color: var(--faint);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .nav-label:first-child { margin-top: 4px; }

        .side nav a, .side button, .button {
            display: flex;
            align-items: center;
            gap: 10px;
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

        .side .theme-switch {
            display: inline-flex;
            gap: 0;
            width: auto;
            padding: 0;
            border-radius: 0;
            background: transparent;
            font-weight: 400;
        }

        .side nav a.on { background: var(--ink); color: var(--card); }
        .side nav a.map-link { color: var(--pin); }

        .nav-count {
            margin-left: auto;
            min-width: 20px;
            padding: 1px 6px;
            border-radius: 999px;
            background: var(--pin);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        .side nav a.on .nav-count { background: var(--card); color: var(--ink); }

        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--pin);
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }

        .message-body { white-space: pre-wrap; margin: 0; line-height: 1.5; }

        .icon { width: 16px; height: 16px; flex: none; display: block; }
        .side .icon { width: 18px; height: 18px; }
        .sprite { position: absolute; width: 0; height: 0; overflow: hidden; }

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

        .page-head .actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-left: auto;
        }

        .page-head .actions form,
        .cell-actions form { display: flex; margin: 0; }

        .page-head .actions a,
        .page-head .actions button,
        .cell-actions a,
        .cell-actions button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 0;
            border-radius: 999px;
            background: var(--field);
            color: var(--ink);
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            padding: 8px 12px;
            cursor: pointer;
            white-space: nowrap;
        }

        .cell-actions {
            display: inline-flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            vertical-align: middle;
        }

        .cell-actions a,
        .cell-actions button { font-size: 13px; padding: 6px 10px; }

        .page-head .actions .solid,
        .cell-actions .solid,
        .button.solid { background: var(--ink); color: var(--card); }

        .page-head .actions .danger,
        .cell-actions .danger,
        .button.danger { background: #c5362b; color: #fff; }

        .page-head .actions .good,
        .cell-actions .good { background: #1f7a4d; color: #fff; }

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

        .status { background: #e7f0e4; color: #1c1b19; }
        .error { background: #fde8e2; color: #1c1b19; }

        html[data-theme="dark"] .status { background: #1a2e24; color: #b7e4c7; }
        html[data-theme="dark"] .error { background: #3a221c; color: #f6f4ef; }

        table { width: 100%; border-collapse: collapse; }

        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        th { color: var(--muted); font-size: 13px; font-weight: 700; }
        th.actions, td.actions { text-align: right; }
        td.actions { width: 1%; vertical-align: middle; }
        td.date { font-size: 13px; white-space: nowrap; color: var(--ink); }
        .subdate {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 16px;
        }

        .filters a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid var(--line);
            border-radius: 999px;
            color: var(--muted);
            font-weight: 700;
            text-decoration: none;
            padding: 6px 12px;
        }

        .filters a.on {
            border-color: var(--ink);
            background: var(--ink);
            color: var(--card);
        }

        .filters .count {
            min-width: 1.2em;
            text-align: center;
        }

        .wrap { overflow: auto; }

        .pill {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 13px;
            font-weight: 700;
            background: #e7f0e4;
            color: #1c1b19;
        }

        .pill.off { background: #fde8e2; color: var(--pin); }

        html[data-theme="dark"] .pill { background: #1a2e24; color: #b7e4c7; }
        html[data-theme="dark"] .pill.off { background: #3a221c; color: #e85d2a; }

        label { display: block; margin-top: 12px; font-weight: 700; }

        input {
            width: min(360px, 100%);
            margin-top: 6px;
            border: 0;
            border-radius: 14px;
            background: var(--field);
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
        .story { margin: 14px 0 0; white-space: pre-wrap; line-height: 1.45; }

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
            background: var(--field);
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

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat { margin: 0; }
        .stat b { display: block; font-size: 28px; letter-spacing: -0.03em; }
        .stat span { color: var(--muted); font-weight: 700; }

        h2 { margin: 8px 0 12px; font-size: 20px; }

        .switch {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
        }

        .switch input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .switch i {
            width: 44px;
            height: 26px;
            border-radius: 999px;
            background: var(--line);
            position: relative;
            flex: none;
        }

        .switch i::after {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--field);
        }

        .switch input:checked + i { background: var(--ink); }
        .switch input:checked + i::after { transform: translateX(18px); }
        .switch input:focus-visible + i { outline: 2px solid var(--pin); outline-offset: 2px; }

        @media (max-width: 800px) {
            .shell { flex-direction: column; }
            .side { width: auto; height: auto; position: static; border-right: 0; border-bottom: 1px solid var(--line); }
            main { padding: 20px 16px 48px; }
            .page-head { flex-direction: column; }
            .stats { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    @include('admin.icons')
    <div class="shell">
        <aside class="side">
            <div class="brand-row">
                <a class="mark" href="{{ route('admin.devices') }}"><img src="{{ asset('brand/icon-192.png') }}" alt="" width="28" height="28"><span><i>KO</i>staa</span></a>
                <button type="button" class="theme-switch" id="theme-switch" aria-pressed="false" aria-label="Тъмен режим"><i aria-hidden="true"></i></button>
            </div>
            <nav>
                @include('admin.nav')
            </nav>
            <div class="side-foot">
                <span class="who">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit">@include('admin.icon', ['name' => 'out']) Изход</button>
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
    <script>
        (function () {
            var button = document.getElementById('theme-switch');
            if (!button) return;
            function paint(dark) {
                if (dark) document.documentElement.setAttribute('data-theme', 'dark');
                else document.documentElement.removeAttribute('data-theme');
                button.setAttribute('aria-pressed', dark ? 'true' : 'false');
            }
            paint(document.documentElement.getAttribute('data-theme') === 'dark');
            button.addEventListener('click', function () {
                var dark = document.documentElement.getAttribute('data-theme') !== 'dark';
                paint(dark);
                try { localStorage.setItem('kostaa-theme', dark ? 'dark' : 'light'); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
