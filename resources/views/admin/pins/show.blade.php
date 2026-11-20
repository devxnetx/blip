@extends('admin.layout')

@section('title', $pin->message)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $pin->message }}</h1>
            <p class="lede">{{ \App\Models\Category::label($pin->category) }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.pins') }}">@include('admin.icon', ['name' => 'pin']) Пинове</a>
            @if ($pin->expires_at->isFuture() && $pin->kind === \App\Models\Pin::KIND_UNASSIGNED)
                <a class="good" href="{{ route('map') }}">@include('admin.icon', ['name' => 'pin']) Място</a>
            @elseif ($pin->latitude !== null && $pin->longitude !== null)
                <a class="solid" href="{{ route('map', ['pin' => $pin->id]) }}">@include('admin.icon', ['name' => 'map']) Карта</a>
            @endif
            @if ($pin->expires_at->isFuture())
                <form method="POST" action="{{ route('admin.pins.hide', $pin) }}" onsubmit="return confirm('Скриваш ли пина от картата? Той остава в историята.')">
                    @csrf
                    <button class="danger" type="submit">@include('admin.icon', ['name' => 'hide']) Скрий</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="facts">
            <div>
                <b>
                    @if (! $pin->expires_at->isFuture())
                        Скрит
                    @elseif ($pin->kind === \App\Models\Pin::KIND_UNASSIGNED)
                        Без място
                    @elseif ($pin->kind === \App\Models\Pin::KIND_UPCOMING)
                        Предстоящо
                    @else
                        Активен
                    @endif
                </b>
                <span>статус</span>
            </div>
            <div><b>{{ $pin->sourceLabel() ?: '—' }}</b><span>източник</span></div>
            <div><b>{{ $pin->starts_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') ?: '—' }}</b><span>начало</span></div>
            <div><b>{{ $pin->expires_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') ?: '—' }}</b><span>край</span></div>
        </div>
        <p class="meta">
            Място:
            @if ($pin->latitude !== null && $pin->longitude !== null)
                {{ number_format($pin->latitude, 5, '.', '') }}, {{ number_format($pin->longitude, 5, '.', '') }}
            @else
                няма
            @endif
        </p>
        <p class="meta">
            Връзка:
            @if ($pin->source_url)
                <a href="{{ $pin->source_url }}" rel="noopener noreferrer" target="_blank">{{ $pin->source_url }}</a>
            @else
                —
            @endif
        </p>
        <p class="meta">
            Устройство:
            @if ($pin->device)
                <a href="{{ route('admin.devices.show', $pin->device) }}">#{{ $pin->device->id }} {{ $pin->device->label }}</a>
            @else
                —
            @endif
        </p>
        <p class="meta">Създаден {{ $pin->created_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</p>
    </div>
@endsection
