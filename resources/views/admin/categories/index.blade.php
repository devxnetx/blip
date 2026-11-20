@extends('admin.layout')

@section('title', 'Категории')

@section('content')
    <div class="page-head">
        <div>
            <h1>Категории</h1>
            <p class="lede">Името се вижда на картата и в телефона. Кодът се създава от името и после не се сменя, за да останат старите пинове вързани.</p>
        </div>
        <div class="actions">
            <a class="solid" href="#new-category">Нова категория</a>
        </div>
    </div>

    <div class="card wrap">
        <table>
            <thead>
                <tr>
                    <th>Име</th>
                    <th>Код</th>
                    <th>Ред</th>
                    <th>Пинове</th>
                    <th class="actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td>{{ $category->position }}</td>
                        <td>{{ $category->pins_count }}</td>
                        <td class="actions">
                            <a href="{{ route('admin.categories.show', $category) }}">Преглед</a>
                            <a href="{{ route('admin.categories.edit', $category) }}">Редакция</a>
                            @if ($category->pins_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Изтриваш ли тази категория?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="danger" type="submit">Изтрий</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card" id="new-category">
        <h2>Нова категория</h2>
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            <label for="name">Име</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="40" required>
            <label for="position">Ред</label>
            <input id="position" name="position" type="number" min="1" max="999" value="{{ old('position') }}" placeholder="след последния">
            <div class="form-actions">
                <button type="submit">Добави</button>
            </div>
        </form>
    </div>
@endsection
