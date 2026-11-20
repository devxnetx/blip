@if ($page->hasPages())
    <p class="pager">
        @if ($page->previousPageUrl())
            <a href="{{ $page->previousPageUrl() }}">Назад</a>
        @endif
        <span>{{ $page->currentPage() }} / {{ $page->lastPage() }}</span>
        @if ($page->nextPageUrl())
            <a href="{{ $page->nextPageUrl() }}">Напред</a>
        @endif
    </p>
@endif
