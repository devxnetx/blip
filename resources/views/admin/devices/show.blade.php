@extends('admin.layout')

@section('title', 'Устройство #'.$device->id)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $device->label ?: 'Устройство' }} #{{ $device->id }}</h1>
            <p class="lede">Номерът е даден на този телефон. Отпечатъкът е скритият му идентификатор. Пиновете остават тук и след като изтекат.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.devices.edit', $device) }}">Редакция</a>
            @if ($device->isBanned())
                <form method="POST" action="{{ route('admin.devices.unban', $device) }}">
                    @csrf
                    <button class="solid" type="submit">Върни</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.devices.ban', $device) }}" onsubmit="return confirm('Забраняваш ли това устройство? Активните пинове изчезват от картата.')">
                    @csrf
                    <button class="danger" type="submit">Забрани</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="facts">
            <div><b>{{ $device->pins_count }}</b><span>пина общо</span></div>
            <div><b>{{ $device->active_pins_count }}</b><span>активни</span></div>
            <div><b>{{ $device->first_seen_at?->format('d.m.Y') ?: '—' }}</b><span>първи път</span></div>
            <div><b>{{ $device->last_seen_at?->format('d.m.Y H:i') ?: '—' }}</b><span>последно</span></div>
        </div>
        <p class="meta">Отпечатък <code>{{ $device->device_hash }}</code></p>
        @if ($device->isBanned())
            <p class="meta">Забранено на {{ $device->banned_at->format('d.m.Y H:i') }}. Скритите пинове остават изтекли, дори ако върнеш достъпа.</p>
        @endif
    </div>

    <div class="card wrap">
        <h2>Пинове</h2>
        @if ($pins->isEmpty())
            <p>Няма пинове.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Съобщение</th>
                        <th>Категория</th>
                        <th>Място</th>
                        <th>Създаден</th>
                        <th>Статус</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pins as $pin)
                        <tr>
                            <td>{{ $pin->message }}</td>
                            <td>{{ \App\Models\Category::label($pin->category) }}</td>
                            <td>{{ number_format($pin->latitude, 5, '.', '') }}, {{ number_format($pin->longitude, 5, '.', '') }}</td>
                            <td>{{ $pin->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($pin->expires_at->isFuture())
                                    <span class="pill">Активен</span>
                                @else
                                    <span class="pill off">Скрит</span>
                                @endif
                            </td>
                            <td class="actions">
                                @if ($pin->expires_at->isFuture())
                                    <a href="{{ route('map', ['pin' => $pin->id]) }}">Преглед</a>
                                    <form method="POST" action="{{ route('admin.pins.hide', $pin) }}" onsubmit="return confirm('Скриваш ли пина от картата? Той остава в историята.')">
                                        @csrf
                                        <button type="submit">Скрий</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @include('admin.pager', ['page' => $pins])
@endsection
