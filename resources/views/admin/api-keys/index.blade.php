@extends('admin.layout')

@section('title', 'API ключове')

@section('content')
    <div class="page-head">
        <div>
            <h1>API ключове</h1>
            <p class="lede">Ключ за клиент, който е платил. Той го праща като bearer token към /api/v1.</p>
        </div>
    </div>

    @if ($token)
        <div class="card">
            <h2>Новият ключ</h2>
            <p class="lede">Копирай го сега. След като излезеш от тази страница, остава само началото му.</p>
            <p><code>{{ $token }}</code></p>
        </div>
    @endif

    <div class="card">
        <h2>Нов ключ</h2>
        <form method="POST" action="{{ route('admin.api-keys.store') }}">
            @csrf
            <label for="name">Клиент</label>
            <input id="name" name="name" value="{{ old('name') }}" maxlength="80" required>
            <div class="form-actions">
                <button type="submit">Създай</button>
            </div>
        </form>
    </div>

    <div class="card wrap">
        <table>
            <thead>
                <tr>
                    <th>Клиент</th>
                    <th>Начало</th>
                    <th>Последно</th>
                    <th>Състояние</th>
                    <th class="actions">Действия</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($keys as $key)
                    <tr>
                        <td>{{ $key->name }}</td>
                        <td><code>{{ $key->token_prefix }}</code></td>
                        <td class="date">{{ $key->last_used_at?->timezone('Europe/Sofia')->format('d.m.Y H:i') ?? '—' }}</td>
                        <td>{{ $key->revoked_at ? 'Спрян' : 'Активен' }}</td>
                        <td class="actions">
                            @if (! $key->revoked_at)
                                <form method="POST" action="{{ route('admin.api-keys.revoke', $key) }}" onsubmit="return confirm('Спираш ли този ключ?')">
                                    @csrf
                                    <button class="danger" type="submit">Спри</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Още няма ключове.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
