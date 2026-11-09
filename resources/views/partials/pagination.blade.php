{{-- Pagination styled for this app. Laravel's default pager emits Tailwind
     utility classes, which render unstyled here since the admin layout ships
     plain CSS. --}}
@if ($paginator->hasPages())
    <style>
        .pg { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .pg a, .pg span { display:inline-flex; align-items:center; justify-content:center; min-width:38px; height:38px;
                          padding:0 12px; border-radius:10px; font-size:0.88rem; font-weight:700; text-decoration:none; }
        .pg a { background:#fff; border:1px solid #e2e8f0; color:#334155; }
        .pg a:hover { border-color:#cbd5e1; background:#f8fafc; }
        .pg .pg-on { background:#0b6a4a; color:#fff; border:1px solid #0b6a4a; }
        .pg .pg-off { color:#cbd5e1; border:1px solid #f1f5f9; background:#fff; }
        .pg .pg-gap { color:#94a3b8; border:none; min-width:auto; padding:0 4px; }
        .pg-info { margin-left:auto; color:#64748b; font-size:0.85rem; }
    </style>

    <nav class="pg" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="pg-off" aria-disabled="true">&larr;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&larr;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pg-gap">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pg-on" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rarr;</a>
        @else
            <span class="pg-off" aria-disabled="true">&rarr;</span>
        @endif

        <span class="pg-info">
            Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </span>
    </nav>
@endif
