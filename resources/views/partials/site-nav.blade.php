<nav class="site-nav">
    <a class="brand" href="{{ url('/') }}">Kostaa</a>
    <div class="links">
        <a href="{{ url('/') }}" @class(['is-on' => request()->is('/')])>Начало</a>
        <a href="{{ route('about') }}" @class(['is-on' => request()->routeIs('about')])>За нас</a>
        <a href="{{ route('map') }}">Картата</a>
    </div>
</nav>
