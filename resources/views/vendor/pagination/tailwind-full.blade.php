@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-disabled">&laquo; Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm">&laquo; Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm">Next &raquo;</a>
            @else
                <span class="btn btn-sm btn-disabled">Next &raquo;</span>
            @endif
        </div>

        <div class="hidden sm:flex sm:items-center sm:gap-xs">
            {{-- First --}}
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-disabled" aria-disabled="true">&laquo; First</span>
            @else
                <a href="{{ $paginator->url(1) }}" class="btn btn-sm">&laquo; First</a>
            @endif

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-disabled" aria-disabled="true">&lsaquo; Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm">&lsaquo; Prev</a>
            @endif

            {{-- Page Numbers --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="btn btn-sm btn-disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-sm bg-primary-container text-on-primary border-none" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-sm btn-ghost">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm">Next &rsaquo;</a>
            @else
                <span class="btn btn-sm btn-disabled" aria-disabled="true">Next &rsaquo;</span>
            @endif

            {{-- Last --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="btn btn-sm">Last &raquo;</a>
            @else
                <span class="btn btn-sm btn-disabled" aria-disabled="true">Last &raquo;</span>
            @endif
        </div>
    </nav>
@endif
