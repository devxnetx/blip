<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Kostaa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sofia+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @include('partials.icons')
    <style>
        :root { --ink: #1c1b19; --muted: #5e5a54; --card: #f6f4ef; --pin: #c2410c; --paper: #e7e4de; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--paper);
            color: var(--ink);
            font-family: "Sofia Sans", sans-serif;
        }
        form {
            width: min(420px, calc(100% - 32px));
            background: var(--card);
            border-radius: 22px;
            padding: 28px;
        }
        .mark { display: flex; flex-direction: column; align-items: center; gap: 8px; margin: 0; font-size: 28px; letter-spacing: -0.04em; }
        .mark img { width: 32px; height: 42px; }
        .mark i { color: var(--pin); font-style: normal; }
        p { color: var(--muted); }
        label { display: block; margin-top: 14px; font-weight: 700; }
        input {
            width: 100%;
            margin-top: 6px;
            border: 0;
            border-radius: 14px;
            background: #fff;
            padding: 12px 14px;
            font: inherit;
            color: var(--ink);
        }
        button {
            width: 100%;
            margin-top: 18px;
            min-height: 48px;
            border: 0;
            border-radius: 14px;
            background: var(--ink);
            color: var(--card);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .error { color: var(--pin); font-weight: 700; }
    </style>
</head>
<body>
    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <h1 class="mark"><img src="{{ asset('brand/icon.svg') }}" alt="" width="32" height="42"><span><i>KO</i>staa</span></h1>
        <p>Вход за администратори.</p>
        @if ($errors->any())
            <p class="error">{{ $errors->first() }}</p>
        @endif
        <label for="name">Име</label>
        <input id="name" name="name" value="{{ old('name') }}" autocomplete="username" required>
        <label for="password">Парола</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Влез</button>
    </form>
</body>
</html>
