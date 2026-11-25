<nav class="site-nav">
    <div class="brand-side">
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('brand/icon-192.png') }}" alt="" width="36" height="36">
            <span><i>KO</i>staa</span>
        </a>
        <button type="button" class="theme-switch" id="theme-switch" aria-pressed="false" aria-label="Тъмен режим">
            <i aria-hidden="true"></i>
        </button>
    </div>
    <div class="links">
        <a href="{{ url('/') }}" @class(['is-on' => request()->is('/')])>Начало</a>
        <a href="{{ route('changelog') }}" @class(['is-on' => request()->routeIs('changelog')])>Промени</a>
        <a href="{{ route('how') }}" @class(['is-on' => request()->routeIs('how')])>Как работи</a>
        <a href="{{ route('contact') }}" @class(['is-on' => request()->routeIs('contact')])>Контакт</a>
        <a href="{{ route('support') }}" @class(['is-on' => request()->routeIs('support')])>Подкрепи ни</a>
        @unless (\App\Support\Phone::is(request()->userAgent()))
            <a href="{{ route('pins') }}" @class(['is-on' => request()->routeIs('pins', 'pins.city', 'pins.show')])>Пинове</a>
        @endunless
        <a class="map-link" href="{{ route('map') }}">Картата</a>
    </div>
</nav>
