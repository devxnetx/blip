<svg class="nav-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <symbol id="nav-map" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></symbol>
    <symbol id="nav-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></symbol>
    <symbol id="nav-history" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></symbol>
    <symbol id="nav-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></symbol>
    <symbol id="nav-changelog" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></symbol>
    <symbol id="nav-how" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></symbol>
    <symbol id="nav-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22 6 12 13 2 6"/></symbol>
    <symbol id="nav-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></symbol>
    <symbol id="nav-discord" viewBox="0 0 24 24" fill="currentColor"><path d="M19.27 5.33A17.4 17.4 0 0 0 15 4a.1.1 0 0 0-.1.05c-.18.33-.39.76-.53 1.09a16.1 16.1 0 0 0-4.74 0c-.14-.34-.36-.76-.54-1.09A.1.1 0 0 0 9 4a17.4 17.4 0 0 0-4.27 1.33.09.09 0 0 0-.04.03C2.2 9.05 1.5 12.67 1.84 16.24c0 .03.02.06.05.07 1.8 1.32 3.53 2.12 5.24 2.65a.1.1 0 0 0 .11-.04c.4-.55.76-1.13 1.07-1.74a.1.1 0 0 0-.05-.14 11.2 11.2 0 0 1-1.53-.73.1.1 0 0 1-.01-.17c.1-.08.2-.16.3-.24a.1.1 0 0 1 .1-.01c3.2 1.46 6.66 1.46 9.82 0a.1.1 0 0 1 .1.01c.1.08.2.16.3.24a.1.1 0 0 1-.01.17c-.49.28-1 .52-1.53.73a.1.1 0 0 0-.05.14c.32.61.68 1.19 1.07 1.74a.1.1 0 0 0 .11.04c1.72-.53 3.45-1.33 5.24-2.65a.1.1 0 0 0 .05-.07c.41-4.2-.69-7.79-2.9-10.88a.08.08 0 0 0-.04-.03zM8.52 14.1c-.96 0-1.75-.88-1.75-1.96s.77-1.96 1.75-1.96 1.76.88 1.75 1.96c0 1.08-.77 1.96-1.75 1.96zm6.96 0c-.96 0-1.75-.88-1.75-1.96s.78-1.96 1.75-1.96 1.75.88 1.75 1.96c.01 1.08-.78 1.96-1.75 1.96z"/></symbol>
</svg>
<nav class="site-nav">
    <div class="brand-side">
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('brand/icon-192.png') }}" alt="" width="36" height="36">
            <span><i>KO</i>staa</span>
        </a>
        <button type="button" class="theme-switch" id="theme-switch" aria-pressed="false" aria-label="Тъмен режим">
            <i aria-hidden="true"></i>
        </button>
        <div class="links links-place">
            @unless (\App\Support\Phone::is(request()->userAgent()))
                <a href="{{ route('pins') }}" @class(['is-on' => request()->routeIs('pins', 'pins.city', 'pins.show')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-pin"></use></svg>Пинове</a>
            @endunless
            <a href="{{ route('map') }}" @class(['map-link', 'is-on' => request()->routeIs('map')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-map"></use></svg>Картата</a>
            <a class="discord-link" href="https://discord.com/channels/1574875809603133562" target="_blank" rel="noopener noreferrer"><svg class="nav-icon" aria-hidden="true"><use href="#nav-discord"></use></svg>Discord</a>
            <a href="{{ route('changelog') }}" @class(['is-on' => request()->routeIs('changelog')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-changelog"></use></svg>Changelog</a>
        </div>
    </div>
    <div class="links">
        <a href="{{ url('/') }}" @class(['is-on' => request()->is('/')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-home"></use></svg>Начало</a>
        <a href="{{ route('how') }}" @class(['is-on' => request()->routeIs('how')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-how"></use></svg>Как работи</a>
        <a href="{{ route('contact') }}" @class(['is-on' => request()->routeIs('contact')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-mail"></use></svg>Контакт</a>
        <a href="{{ route('support') }}" @class(['is-on' => request()->routeIs('support')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-heart"></use></svg>Подкрепи ни</a>
        <a href="{{ route('history') }}" @class(['is-on' => request()->routeIs('history')])><svg class="nav-icon" aria-hidden="true"><use href="#nav-history"></use></svg>История</a>
    </div>
</nav>
