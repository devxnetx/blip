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
    @include('partials.social')
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('brand/icon-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sofia+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --paper: #e7e4de;
            --ink: #1c1b19;
            --muted: #5e5a54;
            --line: #cfcabe;
            --card: #f6f4ef;
            --pin: #c2410c;
            --field: #fff;
            --pin-muted: #cfcabe;
            --notice-bg: #e7f2ea;
            --notice-ink: #1f7a4d;
            --faint: #8a847b;
            color-scheme: light;
        }

        html[data-theme="dark"] {
            --paper: #12110f;
            --ink: #f6f4ef;
            --muted: #cfcabe;
            --line: #3a3834;
            --card: #1c1b19;
            --pin: #e85d2a;
            --field: #2a2825;
            --pin-muted: #5e5a54;
            --notice-bg: #1a2e24;
            --notice-ink: #b7e4c7;
            --faint: #8a847b;
            color-scheme: dark;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background: var(--paper);
            font-family: "Sofia Sans", sans-serif;
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

        .brand-side {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: none;
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

        .nav-sprite { position: absolute; width: 0; height: 0; overflow: hidden; }

        .nav-icon { width: 16px; height: 16px; flex: none; display: block; }

        .links { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }

        .links-place { margin-left: 4px; }

        .links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .links a.is-on { background: var(--ink); color: var(--card); }

        .links-place a {
            color: var(--pin);
            background: rgba(194, 65, 12, 0.14);
        }

        .links-place a:hover { background: rgba(194, 65, 12, 0.24); }

        .links-place a.discord-link {
            color: #5865f2;
            background: rgba(88, 101, 242, 0.14);
        }

        .links-place a.discord-link:hover { background: rgba(88, 101, 242, 0.24); }

        .theme-switch {
            display: inline-flex;
            align-items: center;
            padding: 0;
            border: 0;
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

        main {
            width: min(760px, calc(100% - 40px));
            margin: 0 auto;
            padding: 48px 0 80px;
        }

        h1 {
            margin: 0;
            font-family: "Sofia Sans", sans-serif;
            font-size: clamp(36px, 5vw, 52px);
            font-weight: 600;
            letter-spacing: -0.03em;
            line-height: 1.15;
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
            background: var(--notice-bg);
            color: var(--notice-ink);
            font-weight: 600;
        }

        form.block label {
            display: block;
            margin-top: 14px;
            font-weight: 700;
            font-size: 14px;
        }

        form.block input, form.block textarea, form.block select {
            width: 100%;
            margin-top: 6px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: var(--field);
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

        .pays { list-style: none; margin: 18px 0 0; padding: 0; display: grid; gap: 12px; }

        .pay {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
        }

        .pay em { color: var(--muted); font-style: normal; }

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
            .site-nav { align-items: flex-start; flex-wrap: wrap; padding: 12px 14px; }
            .brand-side, .links { width: 100%; }
            .links-place { margin-left: 0; }
            main { width: min(760px, calc(100% - 28px)); padding-top: 32px; }
        }

        body.is-wide {
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        main.is-wide {
            width: auto;
            margin: 0;
            padding: 0;
            flex: 1;
            min-height: 0;
            overflow: auto;
        }

        .admin-scrim {
            position: fixed;
            inset: 0;
            z-index: 740;
            border: 0;
            padding: 0;
            background: rgba(28, 27, 25, 0.28);
            cursor: pointer;
        }

        .admin-scrim[hidden] { display: none !important; }

        .admin-dock {
            position: fixed;
            z-index: 750;
            top: 50%;
            left: 0;
            display: flex;
            align-items: center;
            transform: translate(-100%, -50%) translateX(44px);
            transition: transform 0.2s ease;
        }

        .admin-dock.is-open { transform: translate(0, -50%); }

        .admin-cog {
            width: 44px;
            height: 64px;
            flex: none;
            display: grid;
            place-items: center;
            border: 0;
            border-radius: 0 14px 14px 0;
            background: var(--pin);
            color: #fff;
            cursor: pointer;
            box-shadow: 2px 8px 18px rgba(28, 27, 25, 0.22);
        }

        .admin-cog svg { width: 22px; height: 22px; display: block; }
        .admin-cog:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

        .admin-menu {
            width: min(280px, calc(100vw - 60px));
            max-height: calc(100vh - 32px);
            overflow: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 12px;
            background: var(--card);
            color: var(--ink);
            border-radius: 0 18px 18px 0;
            box-shadow: 0 18px 40px rgba(28, 27, 25, 0.2);
        }

        .admin-menu .nav-label {
            margin: 14px 12px 2px;
            color: var(--faint);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .admin-menu .nav-label:first-child { margin-top: 0; }

        .admin-menu a,
        .admin-menu button {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
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

        .admin-menu a.on { background: var(--ink); color: var(--card); }
        .admin-menu a.map-link { color: var(--pin); }
        .admin-menu a.on.map-link { color: var(--card); }

        .admin-menu .nav-count {
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

        .admin-menu a.on .nav-count { background: var(--card); color: var(--ink); }
        .admin-menu .icon { width: 18px; height: 18px; flex: none; display: block; }
        .sprite { position: absolute; width: 0; height: 0; overflow: hidden; }

        .admin-foot {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
        }

        .admin-foot .who {
            display: block;
            padding: 0 12px 4px;
            color: var(--muted);
            font-weight: 700;
        }
    </style>
    @stack('head')
</head>
<body @class(['is-wide' => $wide ?? false])>
    @include('partials.site-nav')
    @auth
        @include('partials.admin-dock')
    @endauth
    <main @class(['is-wide' => $wide ?? false])>
        @yield('content')
    </main>
    @stack('scripts')
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
            var dock = document.getElementById('admin-dock');
            var cog = document.getElementById('admin-cog');
            if (dock && cog) {
                var menu = document.getElementById('admin-menu');
                var scrim = document.getElementById('admin-scrim');
                function setAdminMenu(open) {
                    dock.classList.toggle('is-open', open);
                    cog.setAttribute('aria-expanded', open ? 'true' : 'false');
                    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
                    menu.inert = !open;
                    scrim.hidden = !open;
                }
                cog.addEventListener('click', function () {
                    setAdminMenu(!dock.classList.contains('is-open'));
                });
                scrim.addEventListener('click', function () { setAdminMenu(false); });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') setAdminMenu(false);
                });
            }
            button.addEventListener('click', function () {
                var dark = document.documentElement.getAttribute('data-theme') !== 'dark';
                paint(dark);
                try { localStorage.setItem('kostaa-theme', dark ? 'dark' : 'light'); } catch (e) {}
            });
        })();
    </script>
</body>
</html>
