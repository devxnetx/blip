@extends('admin.layout')

@section('title', 'Настройки')

@section('content')
    <div class="page-head">
        <div>
            <h1>Настройки</h1>
            <p class="lede">Нощните задачи се събуждат сами. Превключвателят решава дали тази нощ ще четат сайта.</p>
        </div>
    </div>

    <form class="card" method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <h2>Импорти</h2>

        <label class="switch">
            <input type="checkbox" name="import_sporten_enabled" value="1" @checked($sporten)>
            <i aria-hidden="true"></i>
            <span>Спортен календар</span>
        </label>
        <p class="meta">Всяка нощ в 04:30, българско време.</p>

        <label class="switch">
            <input type="checkbox" name="import_eventibg_enabled" value="1" @checked($eventibg)>
            <i aria-hidden="true"></i>
            <span>EventiBG</span>
        </label>
        <p class="meta">Всяка нощ в 05:10, българско време. Снимките от този сайт не се взимат.</p>

        <label for="import-days">Дни напред</label>
        <input id="import-days" name="import_days_ahead" type="number" min="1" max="90" value="{{ old('import_days_ahead', $days) }}" required>
        <p class="meta">Колко дни напред да се четат и от двата сайта. Събитие, което трае цяла седмица, се записва веднъж.</p>

        <div class="form-actions">
            <button type="submit">Запази</button>
        </div>
    </form>
@endsection
