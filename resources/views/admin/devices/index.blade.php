@extends('admin.layout')

@section('title', 'Устройства')

@section('content')
    <h1>Устройства</h1>
    <p class="lede">При първото отваряне приложението записва свой номер в телефона и го праща с всеки пин. Тук това е устройство. Забрана спира новите пинове от този телефон и скрива активните. Изтриване на приложението прави нов номер.</p>

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
                    </tr>
                </thead>
                <tbody>
                    @foreach ($devices as $device)
                        <tr>
                            <td><a href="{{ route('admin.devices.show', $device) }}">#{{ $device->id }}</a></td>
                            <td>{{ $device->label ?: '—' }}</td>
                            <td>{{ $device->pins_count }}</td>
                            <td>{{ $device->active_pins_count }}</td>
                            <td>{{ $device->last_seen_at?->format('d.m.Y H:i') ?: '—' }}</td>
                            <td>
                                @if ($device->isBanned())
                                    <span class="pill off">Забранено</span>
                                @else
                                    <span class="pill">Активно</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    @include('admin.pager', ['page' => $devices])
@endsection
