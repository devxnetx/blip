@extends('admin.layout')

@section('title', $category->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $category->name }}</h1>
            @if ($category->pins_count > 0)
                <p class="lede">Ползва се от пинове и не може да се изтрие. Името може да се смени, а старите бележки остават с него.</p>
            @else
                <p class="lede">Няма пинове с тази категория.</p>
            @endif
        </div>
        <div class="actions">
            <a href="{{ route('admin.categories.edit', $category) }}">Редакция</a>
            @if ($category->pins_count === 0)
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Изтриваш ли тази категория?')">
                    @csrf
                    @method('DELETE')
                    <button class="danger" type="submit">Изтрий</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="facts">
            <div><b>{{ $category->pins_count }}</b><span>пина</span></div>
            <div><b>{{ $category->position }}</b><span>ред</span></div>
        </div>
        <p class="meta">Код <code>{{ $category->slug }}</code></p>
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
                        <th>Устройство</th>
                        <th>Създаден</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pins as $pin)
                        <tr>
                            <td>{{ $pin->message }}</td>
                            <td>
                                @if ($pin->device)
                                    <a href="{{ route('admin.devices.show', $pin->device) }}">#{{ $pin->device->id }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $pin->created_at?->format('d.m.Y H:i') }}</td>
                            <td>
                                @if ($pin->expires_at->isFuture())
                                    <span class="pill">Активен</span>
                                @else
                                    <span class="pill off">Скрит</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
