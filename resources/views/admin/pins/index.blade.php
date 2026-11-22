@extends('admin.layout')

@section('title', 'Пинове')

@section('content')
    <div class="page-head">
        <div>
            <h1>Пинове</h1>
            <p class="lede">Пин не се трие. «Скрий» го маха от картата и го оставя в историята, както когато времето му изтече.</p>
        </div>
        <div class="actions">
            <a class="solid" href="{{ route('map') }}">@include('admin.icon', ['name' => 'plus']) Нов пин</a>
        </div>
    </div>

    <div class="stats">
        <div class="card stat"><b data-stat="total">{{ $stats['total'] }}</b><span>Общо</span></div>
        <div class="card stat"><b data-stat="active">{{ $stats['active'] }}</b><span>Активни</span></div>
        <div class="card stat"><b data-stat="unassigned">{{ $stats['unassigned'] }}</b><span>Без място</span></div>
        <div class="card stat"><b data-stat="passed">{{ $stats['passed'] }}</b><span>Изтекли</span></div>
    </div>

    <div class="filters">
        @foreach ([
            \App\Models\Pin::KIND_NORMAL => 'Стандартен',
            \App\Models\Pin::KIND_UNASSIGNED => 'Без място',
            \App\Models\Pin::KIND_UPCOMING => 'Предстоящо',
        ] as $slug => $label)
            <a @class(['on' => $kind === $slug]) href="{{ route('admin.pins', ['kind' => $slug]) }}">
                {{ $label }}
                <span class="count">{{ $counts[$slug] }}</span>
            </a>
        @endforeach
    </div>

    <div class="card wrap">
        @if ($pins->isEmpty())
            <p>Няма пинове.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Съобщение</th>
                        <th>Град</th>
                        <th>Източник</th>
                        <th>Връзка</th>
                        <th>Категория</th>
                        <th>Устройство</th>
                        <th>Създаден</th>
                        <th>Статус</th>
                        <th class="actions">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pins as $pin)
                        <tr>
                            <td><a href="{{ route('admin.pins.show', $pin) }}">{{ $pin->message }}</a></td>
                            <td>{{ $pin->cityName() ?: '—' }}</td>
                            <td>{{ $pin->sourceLabel() ?: '—' }}</td>
                            <td>
                                @if ($pin->source_url)
                                    <a href="{{ $pin->source_url }}" rel="noopener noreferrer" target="_blank">Линк</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ \App\Models\Category::label($pin->category) }}</td>
                            <td>
                                @if ($pin->device)
                                    <a href="{{ route('admin.devices.show', $pin->device) }}">#{{ $pin->device->id }}</a>
                                    @if ($pin->device->label)
                                        {{ $pin->device->label }}
                                    @endif
                                    @if ($pin->device->isBanned())
                                        <span class="pill off">забранено</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="date">{{ $pin->created_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</td>
                            <td>
                                @if (! $pin->expires_at->isFuture())
                                    <span class="pill off">Скрит</span>
                                @elseif ($pin->kind === \App\Models\Pin::KIND_UNASSIGNED)
                                    <span class="pill">Без място</span>
                                @elseif ($pin->kind === \App\Models\Pin::KIND_UPCOMING)
                                    <span class="pill">Предстоящо</span>
                                @else
                                    <span class="pill">Активен</span>
                                @endif
                                @if ($pin->starts_at)
                                    <span class="subdate">{{ $pin->starts_at->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</span>
                                @endif
                            </td>
                            <td class="actions">
                                <span class="cell-actions">
                                    <a href="{{ route('admin.pins.show', $pin) }}">@include('admin.icon', ['name' => 'eye']) Преглед</a>
                                    @if ($pin->expires_at->isFuture())
                                        @if ($pin->kind === \App\Models\Pin::KIND_UNASSIGNED)
                                            <a class="good" href="{{ route('map') }}">@include('admin.icon', ['name' => 'pin']) Място</a>
                                        @else
                                            <a href="{{ route('map', ['pin' => $pin->id]) }}">@include('admin.icon', ['name' => 'map']) Карта</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.pins.hide', $pin) }}" onsubmit="return confirm('Скриваш ли пина от картата? Той остава в историята.')">
                                            @csrf
                                            <button class="danger" type="submit">@include('admin.icon', ['name' => 'hide']) Скрий</button>
                                        </form>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @include('admin.pager', ['page' => $pins])
@endsection
