@extends('admin.layout')

@section('title', 'Импорти')

@section('content')
    <div class="page-head">
        <div>
            <h1>Импорти</h1>
            <p class="lede">Всяко нощно четене оставя ред: колко пина влязоха, колко са без място и дали свърши.</p>
        </div>
    </div>

    <div class="card wrap">
        @if ($imports->isEmpty())
            <p>Няма импорти.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Кога</th>
                        <th>Източник</th>
                        <th>Пинове</th>
                        <th>Без място</th>
                        <th>Време</th>
                        <th>Резултат</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($imports as $import)
                        <tr>
                            <td class="date">{{ $import->started_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</td>
                            <td>{{ $import->sourceLabel() }}</td>
                            <td>{{ $import->pins_count }}</td>
                            <td>{{ $import->unassigned_count }}</td>
                            <td>{{ $import->durationLabel() }}</td>
                            <td>
                                @if ($import->finished_at === null)
                                    <span class="pill">Върви</span>
                                @elseif ($import->successful)
                                    <span class="pill">Успешен</span>
                                @else
                                    <span class="pill off">Неуспешен</span>
                                    @if ($import->error)
                                        <div class="meta">{{ $import->error }}</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
