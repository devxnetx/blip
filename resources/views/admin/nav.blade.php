<p class="nav-label">Функции</p>
<a href="{{ route('admin.pins') }}" @class(['on' => request()->routeIs('admin.pins*')])>@include('admin.icon', ['name' => 'pin']) Пинове</a>
<a href="{{ route('admin.devices') }}" @class(['on' => request()->routeIs('admin.devices*')])>@include('admin.icon', ['name' => 'device']) Устройства</a>
<a href="{{ route('admin.categories') }}" @class(['on' => request()->routeIs('admin.categories*')])>@include('admin.icon', ['name' => 'tag']) Категории</a>
<a href="{{ route('map') }}" @class(['map-link', 'on' => request()->routeIs('map')])>@include('admin.icon', ['name' => 'map']) Карта</a>
<p class="nav-label">Система</p>
<a href="{{ route('admin.contacts') }}" @class(['on' => request()->routeIs('admin.contacts*')])>@include('admin.icon', ['name' => 'mail']) Съобщения @if (($unreadContacts ?? 0) > 0)<span class="nav-count">{{ $unreadContacts }}</span>@endif</a>
<a href="{{ route('admin.users') }}" @class(['on' => request()->routeIs('admin.users*')])>@include('admin.icon', ['name' => 'users']) Потребители</a>
<a href="{{ route('admin.api-keys') }}" @class(['on' => request()->routeIs('admin.api-keys*')])>@include('admin.icon', ['name' => 'link']) API ключове</a>
<a href="{{ route('admin.imports') }}" @class(['on' => request()->routeIs('admin.imports')])>@include('admin.icon', ['name' => 'clock']) Импорти</a>
<a href="{{ route('admin.settings') }}" @class(['on' => request()->routeIs('admin.settings*')])>@include('admin.icon', ['name' => 'sliders']) Настройки</a>
<p class="nav-label">Development</p>
<a href="{{ route('admin.changelog') }}" @class(['on' => request()->routeIs('admin.changelog*')])>@include('admin.icon', ['name' => 'edit']) Changelog</a>
<a href="https://trello.com/b/f7V0aAwm/kostaa" target="_blank" rel="noopener noreferrer">@include('admin.icon', ['name' => 'link']) Trello</a>
<a href="https://discord.com/channels/1574875809603133562" target="_blank" rel="noopener noreferrer">@include('admin.icon', ['name' => 'link']) Discord</a>
