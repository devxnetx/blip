@extends('admin.layout')

@section('title', 'Устройства')

@section('content')
    <div class="page-head">
        <div>
            <h1>Устройства</h1>
            <p class="lede">При първото отваряне приложението записва свой номер в телефона и го праща с всеки пин. Тук това е устройство. Забрана спира новите пинове от този телефон и скрива активните. Изтриване на приложението прави нов номер.</p>
        </div>
    </div>

    <div class="card wrap">
        @if ($devices->isEmpty())
            <p>Още няма устройства.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Номер</th>
                        <th>Име</th>
                        <th>Пинове</th>
                        <th>Активни</th>
                        <th>Последно</th>
                        <th>Статус</th>
                        <th class="actions">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <td>#{{ $device->id }}</td>
                            <td>{{ $device->label ?: '—' }}</td>
                            <td>{{ $device->pins_count }}</td>
                            <td>{{ $device->active_pins_count }}</td>
                            <td class="date">{{ $device->last_seen_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') ?: '—' }}</td>
                            <td>
                                @if ($device->isBanned())
                                    <span class="pill off">Забранено</span>
                                @else
                                    <span class="pill">Активно</span>
                                @endif
                            </td>
                            <td class="actions">
                                <span class="cell-actions">
                                    <a href="{{ route('admin.devices.show', $device) }}">@include('admin.icon', ['name' => 'eye']) Преглед</a>
                                    <a href="{{ route('admin.devices.edit', $device) }}">@include('admin.icon', ['name' => 'edit']) Редакция</a>
                                    @if ($device->isBanned())
                                        <form method="POST" action="{{ route('admin.devices.unban', $device) }}">
                                            @csrf
                                            <button class="good" type="submit">@include('admin.icon', ['name' => 'back']) Върни</button>
                                        </form>
                                    @elseif (! $device->isImporter())
                                        <form method="POST" action="{{ route('admin.devices.ban', $device) }}" onsubmit="return confirm('Забраняваш ли това устройство? Активните пинове изчезват от картата.')">
                                            @csrf
                                            <button class="danger" type="submit">@include('admin.icon', ['name' => 'ban']) Забрани</button>
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
    @include('admin.pager', ['page' => $devices])
@endsection
