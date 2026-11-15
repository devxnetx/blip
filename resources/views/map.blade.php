<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Карта — Blip</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
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

        .home {
            top: 16px;
            left: 16px;
            color: var(--ink);
            background: rgba(246, 244, 239, 0.94);
            text-decoration: none;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: -0.04em;
            padding: 6px 12px;
            border-radius: 999px;
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

        #locate { left: 16px; bottom: 24px; background: var(--card); }

        #locate span {
            display: block;
            width: 16px;
            height: 16px;
            margin: 0 auto;
            border: 2px solid var(--ink);
            border-radius: 8px;
        }

        #open-list { right: 16px; bottom: 24px; }

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

        .panel { overflow: auto; }

        .nearby {
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

        .leaflet-control-zoom { margin-top: 64px !important; }
    </style>
</head>
<body>
    <div id="map"></div>
    <a class="home" href="{{ url('/') }}">Bl<i>i</i>p</a>
    <button class="hud" id="locate" type="button" aria-label="Центрирай на моето място"><span></span></button>
    <button class="hud" id="open-list" type="button" aria-label="Пинове наблизо"><span class="bars"><i></i><i></i><i></i></span></button>
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
            <button type="button" data-category="seeking">Търся</button>
            <button type="button" data-category="lost">Загубено</button>
            <button type="button" data-category="warning">Внимание</button>
            <button type="button" data-category="free">Безплатно</button>
            <button type="button" data-category="other">Друго</button>
        </div>
        <p class="faint" id="nearby-status"></p>
        <ul id="nearby-list"></ul>
    </section>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
    <script src="{{ asset('map.js') }}?v={{ filemtime(public_path('map.js')) }}"></script>
</body>
</html>
