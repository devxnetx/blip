<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Карта — Kostaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#1c1b19">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Kostaa">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.css">
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

        html, body { height: 100%; margin: 0; }

        body {
            background: var(--line);
            color: var(--ink);
            font-family: "Schibsted Grotesk", sans-serif;
        }

        #map { position: absolute; inset: 0; background: var(--line); }

        .blip-icon { background: none; border: none; }

        .bubble-wrap {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            width: 168px;
            cursor: pointer;
        }

        .bubble {
            max-width: 168px;
            background: var(--ink);
            color: var(--card);
            border-radius: 14px;
            padding: 8px 10px;
        }

        .bubble img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: cover;
            display: block;
            margin-bottom: 6px;
            background: #3a3834;
        }

        .bubble span {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.3;
        }

        .bubble small {
            display: block;
            margin-top: 3px;
            color: #cfcabe;
            font-size: 11px;
            font-weight: 600;
        }

        .tail {
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 8px solid var(--ink);
        }

        .count {
            height: 46px;
            padding: 0 10px;
            border-radius: 23px;
            background: var(--ink);
            color: var(--card);
            border: 2px solid var(--card);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            cursor: pointer;
        }

        #panel-message { flex: 1; }

        #nearby-list button span { display: flex; flex-direction: column; min-width: 0; }

        .home, .hud, .banner, .empty, .panel, .nearby, .toast {
            position: fixed;
            z-index: 600;
        }

        .top-left {
            position: fixed;
            z-index: 600;
            top: 16px;
            left: 16px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            max-width: calc(100% - 140px);
        }

        body.admin .top-left { max-width: calc(100% - 250px); }

        .top-right {
            position: fixed;
            z-index: 600;
            top: 16px;
            right: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home {
            position: static;
            color: var(--ink);
            background: rgba(246, 244, 239, 0.94);
            text-decoration: none;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.04em;
            padding: 6px 12px;
            border-radius: 999px;
            box-shadow: 0 1px 2px rgba(28, 27, 25, 0.22);
        }

        .home i { color: var(--pin); font-style: normal; }

        .hud {
            width: 48px;
            height: 48px;
            border: 0;
            border-radius: 24px;
            background: var(--ink);
            color: var(--card);
            cursor: pointer;
        }

        #locate {
            right: 16px;
            bottom: 24px;
            background: var(--card);
            box-shadow: 0 1px 2px rgba(28, 27, 25, 0.22);
        }

        #open-list { display: none; }

        #locate span {
            display: block;
            width: 16px;
            height: 16px;
            margin: 0 auto;
            border: 2px solid var(--ink);
            border-radius: 8px;
        }

        .bars { width: 18px; margin: 0 auto; }

        .bars i {
            display: block;
            height: 2px;
            margin: 3px 0;
            border-radius: 1px;
            background: var(--card);
        }

        .banner, .empty {
            top: 64px;
            left: 50%;
            transform: translateX(-50%);
            width: min(420px, calc(100% - 32px));
            border-radius: 12px;
            padding: 12px;
            text-align: center;
        }

        .banner { background: var(--ink); color: var(--card); border: 0; cursor: pointer; }

        .empty {
            background: rgba(246, 244, 239, 0.94);
            pointer-events: none;
        }

        .panel, .nearby {
            top: 16px;
            right: 16px;
            bottom: 16px;
            width: min(380px, calc(100% - 32px));
            background: var(--card);
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 18px 40px rgba(28, 27, 25, 0.16);
        }

        .panel[hidden],
        .nearby[hidden],
        .install[hidden],
        #boot[hidden] {
            display: none !important;
        }

        #boot {
            position: fixed;
            inset: 0;
            z-index: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: opacity 0.45s ease;
        }

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

        .panel { z-index: 650; overflow: auto; }

        .nearby:not([hidden]) {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toast {
            z-index: 700;
            top: 72px;
            left: 50%;
            transform: translateX(-50%);
            width: min(420px, calc(100% - 32px));
            border: 0;
            border-radius: 14px;
            background: var(--ink);
            color: var(--card);
            padding: 12px 14px;
            text-align: left;
            font: inherit;
            cursor: pointer;
        }

        .toast b, .toast span { display: block; }
        .toast span { margin-top: 4px; font-weight: 400; }

        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }

        h2 { margin: 0; font-size: 22px; }

        .muted { color: var(--muted); }
        .faint { color: var(--faint); }

        #panel-photo {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 14px;
            margin: 14px 0;
            background: var(--line);
        }

        .reply {
            background: #fff;
            border-radius: 12px;
            padding: 10px 12px;
            margin-bottom: 8px;
        }

        .reply.mine {
            background: #1c1b19;
            color: #f6f4ef;
        }

        .reply.mine time,
        .reply.mine .only { color: #cfcabe; }

        .reply p { margin: 0; }

        .reply time, .reply .only { display: block; margin-top: 4px; color: var(--faint); font-size: 12px; }

        .check {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin-top: 10px;
            color: var(--ink);
            font-size: 14px;
        }

        .check input { margin-top: 3px; }

        textarea {
            width: 100%;
            min-height: 72px;
            margin-top: 8px;
            border: 0;
            border-radius: 14px;
            background: #fff;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
            resize: vertical;
        }

        .when {
            display: block;
            width: 100%;
            margin-top: 8px;
        }

        label.when {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        input.when {
            border: 0;
            border-radius: 14px;
            background: #fff;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
        }

        .when[hidden] { display: none !important; }

        .count-line { margin: 4px 0 0; text-align: right; color: var(--faint); font-size: 12px; }

        .primary, .dark {
            width: 100%;
            min-height: 48px;
            margin-top: 12px;
            border: 0;
            border-radius: 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .primary { background: var(--pin); color: var(--card); }
        .primary:disabled { opacity: 0.45; cursor: default; }
        .dark { background: var(--ink); color: var(--card); }

        .text-btn {
            margin-top: 14px;
            padding: 6px 0;
            border: 0;
            background: none;
            color: var(--pin);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        .linkish {
            border: 0;
            background: none;
            color: var(--ink);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        #radius { width: 100%; margin: 8px 0 12px; accent-color: var(--pin); }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 0 0 12px;
        }

        .filters button {
            border: 1.5px solid var(--line);
            border-radius: 999px;
            background: transparent;
            color: var(--muted);
            padding: 6px 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .filters button.on {
            border-color: var(--ink);
            background: var(--ink);
            color: var(--card);
        }

        #nearby-list {
            list-style: none;
            margin: 0;
            padding: 0;
            overflow: auto;
            flex: 1;
            min-height: 0;
        }

        .share-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .share-row button,
        #nearby-list .pin-actions button {
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

        .share-row button:hover,
        #nearby-list .pin-actions button:hover {
            background: rgba(194, 65, 12, 0.08);
        }

        #nearby-list li {
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--line);
        }

        #nearby-list .pin-open {
            display: flex;
            gap: 12px;
            align-items: center;
            flex: 1;
            min-width: 0;
            padding: 12px 0;
            border: 0;
            background: none;
            text-align: left;
            font: inherit;
            color: inherit;
            cursor: pointer;
        }

        #nearby-list .pin-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: none;
        }

        #nearby-list img {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--line);
        }

        #nearby-list strong { display: block; }
        #nearby-list .pin-category {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
        }
        #nearby-list small { color: var(--faint); }

        .maplibregl-ctrl-bottom-left { bottom: 10px; }
        .maplibregl-ctrl-attrib { font-size: 11px; }

        .city-bar {
            position: fixed;
            z-index: 600;
            top: 12px;
            left: 132px;
            right: 128px;
            display: flex;
            gap: 8px;
            padding: 4px 2px 8px;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .city-bar::-webkit-scrollbar { display: none; }

        .city-pill, .city-choice {
            flex: none;
            border: 0;
            border-radius: 999px;
            background: rgba(246, 244, 239, 0.94);
            color: var(--ink);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(28, 27, 25, 0.22);
        }

        .city-pill { padding: 8px 14px; }

        #open-cities { background: var(--ink); color: var(--card); }

        #open-cities-mobile { display: none; }

        body.admin .city-bar {
            top: auto;
            bottom: 16px;
            left: 16px;
            right: 80px;
        }

        body.admin .maplibregl-ctrl-bottom-left { bottom: 68px; }

        #open-pins {
            background: var(--pin);
            color: #fff;
        }

        #open-unassigned {
            background: var(--ink);
            color: #fff;
        }

        #unassigned:not([hidden]) { overflow: auto; }

        #unassigned-list {
            list-style: none;
            margin: 16px 0 0;
            padding: 0;
        }

        #unassigned-list button {
            width: 100%;
            text-align: left;
            border: 0;
            border-bottom: 1px solid var(--line);
            background: transparent;
            padding: 12px 0;
            font: inherit;
            color: var(--ink);
            cursor: pointer;
        }

        #unassigned-list button.on strong { color: var(--pin); }

        #unassigned-list strong,
        #unassigned-list small { display: block; }

        #unassigned-list small {
            margin-top: 2px;
            color: var(--faint);
            font-size: 13px;
        }

        #cities-modal { left: 16px; right: auto; }

        #pin-spot { margin: 8px 0 0; font-weight: 700; }

        #city-search {
            width: 100%;
            margin-top: 16px;
            border: 0;
            border-radius: 12px;
            background: #fff;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
        }

        #city-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow: auto;
            margin-top: 16px;
        }

        .city-choice {
            padding: 14px 16px;
            text-align: left;
            box-shadow: none;
            background: transparent;
            border-bottom: 1px solid var(--line);
            border-radius: 0;
        }

        .install {
            position: fixed;
            z-index: 700;
            left: 16px;
            right: 16px;
            bottom: 16px;
            background: var(--card);
            color: var(--ink);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 18px 40px rgba(28, 27, 25, 0.2);
        }

        .install p { margin: 0 0 12px; }

        .install .row { gap: 8px; }

        .install a, .install button {
            border-radius: 999px;
            padding: 10px 14px;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }

        #install-go, .install a {
            background: var(--ink);
            color: var(--card);
            border: 0;
        }

        #install-close {
            background: transparent;
            border: 0;
            color: var(--muted);
        }

        @media (max-width: 800px) {
            #boot { display: none !important; }
            .city-bar { display: none; }
            #open-cities-mobile { display: inline-flex; align-items: center; }
            body.admin #open-cities-mobile {
                position: fixed;
                z-index: 600;
                left: 16px;
                bottom: 24px;
            }
            body:has(#install:not([hidden])) #locate { bottom: 148px; }
            body.admin:has(#install:not([hidden])) #open-cities-mobile { bottom: 236px; }
            .maplibregl-ctrl-bottom-left { display: none; }
            .panel, .nearby, #cities-modal { left: 0; right: 0; top: 0; bottom: 0; width: auto; border-radius: 0; }
        }
    </style>
</head>
<body @class(['admin' => auth()->check()])>
    <div id="boot">
        <div>
            <div class="boot-pin" aria-hidden="true"><span></span></div>
            <div class="boot-word"><i>KO</i>staa</div>
        </div>
    </div>
    <div id="map"></div>
    <div class="top-left">
        <a class="home" href="{{ url('/') }}"><i>KO</i>staa</a>
        <button class="city-pill" id="open-cities-mobile" type="button">Градове</button>
        @auth
            <a class="city-pill" href="{{ route('admin.devices') }}">Админ</a>
            <button class="city-pill" id="open-composer" type="button">Нов пин</button>
        @endauth
    </div>
    <nav class="city-bar" id="city-bar" aria-label="Градове">
        <button class="city-pill" id="open-cities" type="button">Още</button>
    </nav>
    <div class="top-right">
        @auth
            <button class="city-pill" id="open-unassigned" type="button">Без място</button>
        @endauth
        <button class="city-pill" id="open-pins" type="button">Пинове</button>
    </div>
    <button class="hud" id="locate" type="button" aria-label="Центрирай на моето място"><span></span></button>
    <button class="hud" id="open-list" type="button" hidden aria-label="Пинове наблизо"><span class="bars"><i></i><i></i><i></i></span></button>
    <button class="toast" id="toast" type="button" hidden></button>
    <button class="banner" id="banner" type="button" hidden>Картата не успя да зареди пиновете. Опитай пак.</button>
    <p class="empty" id="empty" hidden>Нищо в този изглед.</p>

    <aside class="panel" id="panel" hidden>
        <div class="row">
            <h2 id="panel-message"></h2>
            <button class="linkish" id="panel-close" type="button">Затвори</button>
        </div>
        <p class="muted" id="panel-category" hidden></p>
        <img id="panel-photo" alt="" hidden>
        <p class="muted" id="panel-time"></p>
        @auth
            <button class="dark" id="ban-device" type="button">Забрани устройството</button>
        @endauth
        <div class="share-row">
            <button id="copy-link" type="button">Копирай връзката към пина</button>
            <button id="share-link" type="button">Сподели</button>
        </div>
        <p class="faint" id="copy-status" hidden></p>
        <div id="panel-replies"></div>
        <p class="faint" id="panel-reply-error" hidden></p>
        <p id="panel-sent" hidden>Отговорът е изпратен.</p>
        <form id="reply-form">
            <textarea id="reply-text" maxlength="200" placeholder="Имаш право само на 1 отговор"></textarea>
            <label class="check">
                <input id="reply-private" type="checkbox">
                <span>Видимо само за автора на пина</span>
            </label>
            <p class="count-line" id="reply-count">0/200</p>
            <button class="primary" id="reply-send" type="submit">Изпрати отговор</button>
            <p class="faint" id="reply-form-error" hidden></p>
        </form>
        <button class="text-btn" id="report" type="button">Докладвай</button>
        <p class="faint" id="reported" hidden>Докладвано.</p>
        <button class="dark" id="remove" type="button">Махни пина</button>
    </aside>

    <section class="nearby" id="nearby" hidden>
        <div class="row">
            <h2>Наблизо</h2>
            <button class="linkish" id="nearby-close" type="button">Затвори</button>
        </div>
        <p id="radius-label"><b>5 км</b></p>
        <input id="radius" type="range" min="1" max="30" step="1" value="5" aria-label="Радиус в километри">
        <div class="filters" id="category-filters">
            <button type="button" class="on" data-category="">Всички</button>
            @foreach ($listedCategories as $slug => $name)
                <button type="button" data-category="{{ $slug }}">{{ $name }}</button>
            @endforeach
        </div>
        <p class="faint" id="nearby-status"></p>
        <ul id="nearby-list"></ul>
    </section>

    <section class="nearby" id="cities-modal" hidden>
        <div class="row">
            <h2>Градове</h2>
            <button class="linkish" id="cities-close" type="button">Затвори</button>
        </div>
        <input id="city-search" type="search" placeholder="Търси град" autocomplete="off" aria-label="Търси град">
        <p class="faint" id="city-empty" hidden>Няма такъв град.</p>
        <div id="city-list"></div>
    </section>

    @auth
        <section class="nearby" id="composer" hidden>
            <div class="row">
                <h2>Нов пин</h2>
                <button class="linkish" id="composer-close" type="button">Затвори</button>
            </div>
            <p class="muted">Пиновете от телефона си остават. Този пин се пуска от администратор.</p>
            <form id="admin-pin-form">
                <textarea id="pin-message" maxlength="80" placeholder="Кратко съобщение"></textarea>
                <p class="count-line" id="pin-count">0/80</p>
                <div class="filters" id="composer-categories">
                    @foreach ($listedCategories as $slug => $name)
                        <button type="button" @class(['on' => $slug === $defaultCategory]) data-category="{{ $slug }}">{{ $name }}</button>
                    @endforeach
                </div>
                <div class="filters" id="composer-when">
                    <button type="button" class="on" data-when="normal">Сега</button>
                    <button type="button" data-when="upcoming">Предстоящо</button>
                </div>
                <label class="when" id="pin-start-label" for="pin-start" hidden>Кога започва</label>
                <input class="when" id="pin-start" type="datetime-local" hidden>
                <p class="when faint" id="pin-start-hint" hidden>Вижда се веднага. Изчезва след избраното време от началото.</p>
                <div class="filters" id="composer-durations">
                    <button type="button" data-duration="5m">5 мин</button>
                    <button type="button" class="on" data-duration="1h">1 час</button>
                    <button type="button" data-duration="24h">24 часа</button>
                </div>
                <input id="pin-category" type="hidden" value="{{ $defaultCategory }}">
                <input id="pin-kind" type="hidden" value="normal">
                <input id="pin-duration" type="hidden" value="1h">
                <input id="pin-lat" type="hidden">
                <input id="pin-lng" type="hidden">
                <p id="pin-spot">Няма избрано място.</p>
                <button class="dark" id="pick-spot" type="button">Избери на картата</button>
                <button class="primary" id="pin-send" type="submit">Пусни пин</button>
                <p class="faint" id="pin-error" hidden></p>
            </form>
        </section>

        <section class="nearby" id="unassigned" hidden>
            <div class="row">
                <h2>Без място</h2>
                <button class="linkish" id="unassigned-close" type="button">Затвори</button>
            </div>
            <p class="muted" id="unassigned-hint">Избери събитие, после кликни върху картата.</p>
            <ul id="unassigned-list"></ul>
            <form id="unassigned-form">
                <textarea id="unassigned-message" maxlength="80" placeholder="Кратко съобщение"></textarea>
                <p class="count-line" id="unassigned-count">0/80</p>
                <div class="filters" id="unassigned-categories">
                    @foreach ($listedCategories as $slug => $name)
                        <button type="button" @class(['on' => $slug === $defaultCategory]) data-category="{{ $slug }}">{{ $name }}</button>
                    @endforeach
                </div>
                <label class="when" for="unassigned-start">Дата, по избор</label>
                <input class="when" id="unassigned-start" type="datetime-local">
                <div class="filters" id="unassigned-durations">
                    <button type="button" data-duration="5m">5 мин</button>
                    <button type="button" class="on" data-duration="1h">1 час</button>
                    <button type="button" data-duration="24h">24 часа</button>
                </div>
                <input id="unassigned-category" type="hidden" value="{{ $defaultCategory }}">
                <input id="unassigned-duration" type="hidden" value="1h">
                <p class="faint">Без дата пинът започва, когато му дадеш място.</p>
                <button class="primary" id="unassigned-send" type="submit">Добави</button>
                <p class="faint" id="unassigned-error" hidden></p>
            </form>
        </section>
    @endauth

    <aside class="install" id="install" hidden>
        @if (config('blip.app_store_url') || config('blip.play_store_url'))
            <p>Има приложение за телефона. Може и да добавиш тази страница на началния екран.</p>
            <div class="row">
                @if (config('blip.app_store_url'))
                    <a href="{{ config('blip.app_store_url') }}" rel="noopener">Expo за iPhone</a>
                @endif
                @if (config('blip.play_store_url'))
                    <a href="{{ config('blip.play_store_url') }}" rel="noopener">APK за Android</a>
                @endif
            </div>
        @else
            <p>Добави Kostaa на началния екран и я отваряй като приложение.</p>
        @endif
        <p class="faint" id="install-hint" hidden>На iPhone: бутонът Сподели, после „Добави към началния екран“.</p>
        <div class="row">
            <button id="install-go" type="button">Добави</button>
            <button id="install-close" type="button">Не сега</button>
        </div>
    </aside>

    <script>window.KOSTAA_CATEGORIES = @json($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);</script>
    <script src="https://unpkg.com/maplibre-gl@5.15.0/dist/maplibre-gl.js"></script>
    <script src="{{ asset('map.js') }}?v={{ filemtime(public_path('map.js')) }}"></script>
</body>
</html>
