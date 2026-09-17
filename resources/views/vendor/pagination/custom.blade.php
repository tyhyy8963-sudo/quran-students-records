@if ($paginator->hasPages())
    @php($elements = $paginator->elements())
    <nav aria-label="التنقّل بين الصفحات" dir="rtl" style="margin-top:1rem;">
        <ul class="pagination" style="display:flex; gap:.35rem; list-style:none; padding:0; margin:0; flex-wrap:wrap; justify-content:center;">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link" style="padding:.4rem .8rem; border-radius:6px; opacity:.5;">السابق</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" style="padding:.4rem .8rem; border-radius:6px; text-decoration:none; border:1px solid #ccc;">السابق</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link" style="padding:.4rem .8rem;">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link" style="padding:.4rem .8rem; border-radius:6px; background:#606c38; color:#fff; font-weight:bold;">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}" style="padding:.4rem .8rem; border-radius:6px; text-decoration:none; border:1px solid #ccc;">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" style="padding:.4rem .8rem; border-radius:6px; text-decoration:none; border:1px solid #ccc;">التالي</a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link" style="padding:.4rem .8rem; border-radius:6px; opacity:.5;">التالي</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
