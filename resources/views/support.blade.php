@extends('layouts.site')

@section('content')
    <h1>Подкрепи ни</h1>
    <p class="lede">Kostaa е безплатна. Ако искаш да помогнеш за сървъра и картата, можеш оттук. Не е нужно, за да пускаш или гледаш пинове.</p>

    <div class="pays">
        <article class="pay">
            <strong>Revolut</strong>
            @if ($revolut)
                <p>Плащането се отваря в Revolut.</p>
                <a class="open" href="{{ $revolut }}" rel="noopener noreferrer" target="_blank">Отвори Revolut</a>
            @else
                <p><em>Още няма адрес.</em></p>
            @endif
        </article>

        <article class="pay">
            <strong>Ethereum</strong>
            @if ($ethereum)
                <p>Адресът е за ETH. Провери го, преди да пратиш.</p>
                <p class="addr">{{ $ethereum }}</p>
                <button class="copy" type="button" data-copy="{{ $ethereum }}">Копирай</button>
            @else
                <p><em>Още няма адрес.</em></p>
            @endif
        </article>

        <article class="pay">
            <strong>Solana</strong>
            @if ($solana)
                <p>Адресът е за SOL. Провери го, преди да пратиш.</p>
                <p class="addr">{{ $solana }}</p>
                <button class="copy" type="button" data-copy="{{ $solana }}">Копирай</button>
            @else
                <p><em>Още няма адрес.</em></p>
            @endif
        </article>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(button.dataset.copy || '');
                    const previous = button.textContent;
                    button.textContent = 'Копирано';
                    setTimeout(() => { button.textContent = previous; }, 1200);
                } catch (error) {
                    button.textContent = 'Не се копира';
                }
            });
        });
    </script>
@endpush
