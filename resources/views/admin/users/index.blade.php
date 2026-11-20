@extends('admin.layout')

@section('title', 'Потребители')

@section('content')
    <h1>Потребители</h1>
    <p class="lede">Това са входовете за админ панела. Хората, които пускат пинове от телефона, са в устройствата.</p>

    <div class="card">
        <h2>Нов администратор</h2>
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <label for="name">Име</label>
            <input id="name" name="name" value="{{ old('name') }}" autocomplete="off" required>
            <label for="password">Парола</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <label for="password_confirmation">Пак паролата</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="button solid" type="submit">Добави</button>
        </form>
    </div>

    <div class="card wrap">
        <table>
            <thead>
                <tr>
                    <th>Име</th>
                    <th>Създаден</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->created_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            @if (! auth()->user()->is($user))
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Изтриваш ли този вход?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit">Изтрий</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
