@extends('admin.layout')

@section('title', 'Съобщения')

@section('content')
    <div class="page-head">
        <div>
            <h1>Съобщения</h1>
            <p class="lede">Това са писмата от страницата Контакт. Отварянето маркира писмото като прочетено.</p>
        </div>
    </div>

    @if ($messages->isEmpty())
        <p class="lede">Още няма съобщения.</p>
    @else
        <div class="card wrap">
            <table>
                <thead>
                    <tr>
                        <th>Статус</th>
                        <th>Име</th>
                        <th>Имейл</th>
                        <th>Причина</th>
                        <th>Съобщение</th>
                        <th>Получено</th>
                        <th class="actions">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr>
                            <td>@if ($message->isUnread())<span class="pill">Ново</span>@else Прочетено @endif</td>
                            <td>{{ $message->name }}</td>
                            <td>{{ $message->email }}</td>
                            <td>{{ $message->reasonLabel() }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($message->message, 80) }}</td>
                            <td class="date">{{ $message->created_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</td>
                            <td class="actions">
                                <span class="cell-actions">
                                    <a href="{{ route('admin.contacts.show', $message) }}">@include('admin.icon', ['name' => 'eye']) Прочети</a>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @include('admin.pager', ['page' => $messages])
    @endif
@endsection
