@extends('admin.layout')

@section('title', 'Потребители')

@section('content')
    <div class="page-head">
        <div>
            <h1>Потребители</h1>
            <p class="lede">Това са входовете за админ панела. Хората, които пускат пинове от телефона, са в устройствата.</p>
        </div>
        <div class="actions">
            <a class="solid" href="#new-user">@include('admin.icon', ['name' => 'plus']) Нов администратор</a>
        </div>
    </div>

    <div class="card wrap">
        <table>
            <thead>
                <tr>
                    <th>Име</th>
                    <th>Създаден</th>
                    <th class="actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="date">{{ $user->created_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') }}</td>
                        <td class="actions">
                            <span class="cell-actions">
                                <a href="{{ route('admin.users.show', $user) }}">@include('admin.icon', ['name' => 'eye']) Преглед</a>
                                <a href="{{ route('admin.users.edit', $user) }}">@include('admin.icon', ['name' => 'edit']) Редакция</a>
                                @if (! auth()->user()->is($user))
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Изтриваш ли този вход?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger" type="submit">@include('admin.icon', ['name' => 'trash']) Изтрий</button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card" id="new-user">
        <h2>Нов администратор</h2>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <label for="name">Име</label>
            <input id="name" name="name" value="{{ old('name') }}" autocomplete="off" required>
            <label for="password">Парола</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <label for="password_confirmation">Пак паролата</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <div class="form-actions">
                <button type="submit">Добави</button>
            </div>
        </form>
    </div>
@endsection
