@extends('admin.layout')

@section('title', 'Changelog')

@section('content')
    <div class="page-head">
        <div>
            <h1>Changelog</h1>
            <p class="lede">Публикуваната бележка се вижда на сайта и в Discord канала changelog. Черновата стои само тук.</p>
        </div>
        <div class="actions">
            <a class="solid" href="{{ route('admin.changelog.create') }}">@include('admin.icon', ['name' => 'plus']) Нова промяна</a>
        </div>
    </div>

    <div class="card wrap">
        <table>
            <thead>
                <tr>
                    <th>Заглавие</th>
                    <th>Състояние</th>
                    <th>Дата</th>
                    <th class="actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->title }}</td>
                        <td>
                            @if ($entry->isPublished())
                                <span class="pill">Публикувана</span>
                                @if ($entry->discord_message_id === null)
                                    <span class="subdate">без Discord</span>
                                @endif
                            @else
                                <span class="pill off">Чернова</span>
                            @endif
                        </td>
                        <td class="date">{{ ($entry->published_at ?? $entry->created_at)?->timezone('Europe/Sofia')->format('d.m.Y, H:i') }}</td>
                        <td class="actions">
                            <span class="cell-actions">
                                <a href="{{ route('admin.changelog.edit', $entry) }}">@include('admin.icon', ['name' => 'edit']) Редакция</a>
                                <form method="POST" action="{{ route('admin.changelog.destroy', $entry) }}" onsubmit="return confirm('Изтриваш ли тази промяна?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="danger" type="submit">@include('admin.icon', ['name' => 'trash']) Изтрий</button>
                                </form>
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Няма записани промени.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
