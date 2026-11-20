@extends('admin.layout')

@section('title', 'Редакция на устройство #'.$device->id)

@section('content')
    <div class="page-head">
        <div>
            <h1>Редакция #{{ $device->id }}</h1>
            <p class="lede">Името се вижда само в админ панела. Номерът на телефона не се променя.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.devices.show', $device) }}">@include('admin.icon', ['name' => 'eye']) Преглед</a>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.devices.update', $device) }}">
            @csrf
            @method('PUT')
            <label for="label">Име</label>
            <input id="label" name="label" value="{{ old('label', $device->label) }}" maxlength="40">
            <div class="form-actions">
                <button type="submit">Запази</button>
            </div>
        </form>
    </div>
@endsection
