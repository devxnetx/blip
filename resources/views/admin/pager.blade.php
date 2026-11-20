@if ($page->hasPages())
    <nav class="pager" aria-label="Страници">
        @foreach ($page->onEachSide(1)->linkCollection() as $link)
            @if ($loop->first)
                @if ($link['url'])
                    <a href="{{ $link['url'] }}">Назад</a>
                @else
                    <span class="off">Назад</span>
                @endif
            @elseif ($loop->last)
                @if ($link['url'])
                    <a href="{{ $link['url'] }}">Напред</a>
                @else
                    <span class="off">Напред</span>
                @endif
            @elseif ($link['url'] === null)
                <span class="gap">…</span>
            @elseif ($link['active'])
                <span class="on">{{ $link['label'] }}</span>
            @else
                <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
            @endif
        @endforeach
    </nav>
@endif
