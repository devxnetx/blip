<nav class="site-nav">
    <a class="brand" href="{{ url('/') }}">
        <img src="{{ asset('icons/icon-192.png') }}" alt="" width="36" height="36">
        <span><i>KO</i>staa</span>
    </a>
    <div class="links">
        <a href="{{ url('/') }}" @class(['is-on' => request()->is('/')])>Начало</a>
        <a href="{{ route('about') }}" @class(['is-on' => request()->routeIs('about')])>За нас</a>
        <a href="{{ route('how') }}" @class(['is-on' => request()->routeIs('how')])>Как работи</a>
        <a href="{{ route('contact') }}" @class(['is-on' => request()->routeIs('contact')])>Контакт</a>
        <a href="{{ route('support') }}" @class(['is-on' => request()->routeIs('support')])>Подкрепи ни</a>
        @unless (\App\Support\Phone::is(request()->userAgent()))
            <a href="{{ route('pins') }}" @class(['is-on' => request()->routeIs('pins', 'pins.city', 'pins.show')])>Пинове</a>
        @endunless
        <button type="button" class="theme-switch" id="theme-switch" aria-pressed="false" aria-label="Тъмен режим">
            <span>Тъмен</span>
            <i aria-hidden="true"></i>
        </button>
        <a class="map-link" href="{{ route('map') }}">Картата</a>
    </div>
</nav>
