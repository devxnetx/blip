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
        <p class="meta">Всяка нощ в 05:10, българско време. Снимката на събитието се записва.</p>

        <label class="switch">
            <input type="checkbox" name="import_kupibileti_enabled" value="1" @checked($kupibileti)>
            <i aria-hidden="true"></i>
            <span>Купи билети</span>
        </label>
        <p class="meta">Всяка нощ в 06:00, българско време. Чете всички предстоящи събития. Ограничението в дни не важи тук. Снимката се записва.</p>

        <label class="switch">
            <input type="checkbox" name="import_aktualnitenovini_enabled" value="1" @checked($aktualnitenovini)>
            <i aria-hidden="true"></i>
            <span>Актуалните новини</span>
        </label>
        <p class="meta">Всяка нощ в 05:40, българско време. Клубни участия и концерти. Снимката се записва.</p>

        <label class="switch">
            <input type="checkbox" name="import_facebook_enabled" value="1" @checked($facebook)>
            <i aria-hidden="true"></i>
            <span>Facebook събития</span>
        </label>
        <p class="meta">На 1, 4, 7, 10, 13, 16, 19, 22, 25 и 28 число, в 06:20, българско време. Всички градове от картата. Снимката се записва. Събитие без координати остава без място.</p>

        <label for="import-days">Дни напред</label>
        <input id="import-days" name="import_days_ahead" type="number" min="1" max="90" value="{{ old('import_days_ahead', $days) }}" required>
        <p class="meta">Колко дни напред да се четат Спортен календар, EventiBG, Актуалните новини и Facebook. Купи билети чете целия предстоящ списък. Събитие, което трае цяла седмица, се записва веднъж.</p>

        <h2>Сайт</h2>
        <p class="meta">Връзките се показват на страницата Контакт. Празно поле маха връзката от сайта.</p>

        <label for="social-instagram">Instagram</label>
        <input id="social-instagram" name="social_instagram" type="url" value="{{ old('social_instagram', $links['social_instagram']) }}" placeholder="https://instagram.com/…" maxlength="255">

        <label for="social-facebook">Facebook</label>
        <input id="social-facebook" name="social_facebook" type="url" value="{{ old('social_facebook', $links['social_facebook']) }}" placeholder="https://facebook.com/…" maxlength="255">

        <label for="social-telegram">Telegram</label>
        <input id="social-telegram" name="social_telegram" type="url" value="{{ old('social_telegram', $links['social_telegram']) }}" placeholder="https://t.me/…" maxlength="255">

        <label for="social-tiktok">TikTok</label>
        <input id="social-tiktok" name="social_tiktok" type="url" value="{{ old('social_tiktok', $links['social_tiktok']) }}" placeholder="https://www.tiktok.com/@…" maxlength="255">

        <h2>Подкрепа</h2>
        <p class="meta">Тези стойности се показват на Подкрепи ни. Празно поле маха адреса от сайта.</p>

        <label for="donate-revolut">Revolut</label>
        <input id="donate-revolut" name="donate_revolut" type="url" value="{{ old('donate_revolut', $links['donate_revolut']) }}" placeholder="https://revolut.me/…" maxlength="255">

        <label for="donate-eth">Ethereum</label>
        <input id="donate-eth" name="donate_eth" value="{{ old('donate_eth', $links['donate_eth']) }}" placeholder="0x…" maxlength="42" spellcheck="false" autocomplete="off">

        <label for="donate-sol">Solana</label>
        <input id="donate-sol" name="donate_sol" value="{{ old('donate_sol', $links['donate_sol']) }}" placeholder="Solana адрес" maxlength="44" spellcheck="false" autocomplete="off">

        <div class="form-actions">
            <button type="submit">Запази</button>
        </div>
    </form>
@endsection
