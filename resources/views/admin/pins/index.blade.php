@extends('admin.layout')

@section('title', 'Пинове')

@section('content')
    <h1>Пинове</h1>
    <p class="lede">Всички пинове, включително изтеклите. От картата забраната е върху устройството, което е пуснало пина.</p>

    <div class="card wrap">
        @if ($pins->isEmpty())
            <p>Няма пинове.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Съобщение</th>
                        <th>Категория</th>
                        <th>Устройство</th>
                        <th>Създаден</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pins as $pin)
                        <tr>
                            <td>
                                {{ $pin->message }}
                                @if ($pin->expires_at->isFuture())
                                    <div><a href="{{ route('map', ['pin' => $pin->id]) }}">На картата</a></div>
                                @endif
                            </td>
                            <td>{{ \App\Models\Pin::CATEGORIES[$pin->category] ?? '—' }}</td>
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
                            <td>{{ $pin->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($pin->expires_at->isFuture())
                                    <span class="pill">Активен</span>
                                @else
                                    <span class="pill off">Изтекъл</span>
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
