@extends('admin.layout')

@section('title', 'Нова промяна')

@section('content')
    <div class="page-head">
        <div>
            <h1>Нова промяна</h1>
            <p class="lede">Заглавието и текстът отиват на страницата Changelog и в Discord.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.changelog') }}">@include('admin.icon', ['name' => 'back']) Назад</a>
        </div>
    </div>

    @include('admin.changelog.form', [
        'action' => route('admin.changelog.store'),
        'method' => 'POST',
    ])
@endsection
