@extends('admin.layout')

@section('title', 'Редакция на '.$category->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Редакция</h1>
            <p class="lede">Името и редът се сменят. Кодът <code>{{ $category->slug }}</code> остава.</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.categories.show', $category) }}">Преглед</a>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')
            <label for="name">Име</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" maxlength="40" required>
            <label for="position">Ред</label>
            <input id="position" name="position" type="number" min="1" max="999" value="{{ old('position', $category->position) }}" required>
            <div class="form-actions">
                <button type="submit">Запази</button>
            </div>
        </form>
    </div>
@endsection
