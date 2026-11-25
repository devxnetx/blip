@extends('admin.layout')

@section('title', 'Редакция на промяна')

@section('content')
    <div class="page-head">
        <div>
            <h1>Редакция</h1>
            <p class="lede">Записът обновява съобщението в Discord, ако промяната е публикувана.</p>
        </div>
        <div class="actions">
            <a href="{{ route('changelog') }}#{{ $entry->id }}">@include('admin.icon', ['name' => 'eye']) На сайта</a>
            <a href="{{ route('admin.changelog') }}">@include('admin.icon', ['name' => 'back']) Назад</a>
        </div>
    </div>

    @include('admin.changelog.form', [
        'action' => route('admin.changelog.update', $entry),
        'method' => 'PUT',
    ])
@endsection
