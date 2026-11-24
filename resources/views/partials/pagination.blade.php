@if ($paginator->hasPages())
    @once
        <style>
            .pg {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .pg a,
            .pg span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                padding: 0;
                border-radius: 10px;
                font-size: .86rem;
                font-weight: 700;
                line-height: 1;
                text-decoration: none;
                transition: background-color 160ms ease, border-color 160ms ease,
                    box-shadow 160ms ease, color 160ms ease, transform 160ms ease;
            }

            .pg a {
                border: 1px solid #dbe7e1;
                background: #fff;
                color: #27634f;
                box-shadow: 0 1px 2px rgba(15, 81, 58, .04);
            }

            .pg a:hover {
                border-color: #8bd5b8;
                background: #ecfdf5;
                color: #086b4c;
                box-shadow: 0 5px 12px rgba(8, 124, 91, .12);
                transform: translateY(-1px);
            }

            .pg a:focus-visible {
                outline: 3px solid rgba(19, 184, 134, .24);
                outline-offset: 2px;
            }

            .pg .pg-on {
                border: 1px solid #087c5b;
                background: #087c5b;
                color: #fff;
                box-shadow: 0 5px 12px rgba(8, 124, 91, .18);
            }

            .pg .pg-off {
                border: 1px solid #edf2ef;
                background: #fafcfb;
                color: #b6c7bf;
                cursor: default;
            }

            .pg .pg-gap {
                width: auto;
                min-width: 14px;
                border: 0;
                color: #8aa097;
            }

            .pg svg {
                width: 16px;
                height: 16px;
                stroke: currentColor;
                stroke-width: 1.8;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            @media (max-width: 480px) {
                .pg { gap: 6px; }
                .pg a,
                .pg span { width: 36px; height: 36px; }
                .pg .pg-gap { width: auto; min-width: 12px; }
            }
        </style>
    @endonce

    <nav class="pg" role="navigation" aria-label="Pagination, page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}">
        @if ($paginator->onFirstPage())
            <span class="pg-off" aria-disabled="true" aria-label="Previous page">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6" /></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 12H4m6-6-6 6 6 6" /></svg>
            </a>
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
                        <a href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6" /></svg>
            </a>
        @else
            <span class="pg-off" aria-disabled="true" aria-label="Next page">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6" /></svg>
            </span>
        @endif
    </nav>
@endif
