{{--
    ترقيم الصفحات (S9) — أُعيدت كتابته في S21.

    كان هذا الملف قبل S21 يستعمل تنسيقًا مضمَّنًا (style="...") بالكامل مع ألوان
    وأنصاف أقطار ومسافات مكتوبة حرفيًا (#606c38، #ccc، #fff، 6px، .4rem...) —
    نسخة موازية كاملة خارج نظام التصميم (tokens.css من S3)، بينما كانت الفئتان
    الصحيحتان .pagination-nav/.pagination-list معرَّفتين فعليًا في app.css منذ
    فترة طويلة (قسم "12) بحث وفلترة") دون أن يستعملهما أي قالب — أي أن ترقيم
    الصفحات وحده (يظهر في لوحة الطلاب عند تجاوز 50 طالبًا، وفي سجلّ الطالب عند
    تجاوز 20 سطرًا) كان الموضع الوحيد في كل الواجهة بمظهر مختلف تمامًا عن بقية
    الأزرار والبطاقات، ولا يتغيّر إطلاقًا مع الوضع الليلي (حدود #ccc وخلفية بيضاء
    ضمنية تبقيان كما هما على خلفية داكنة). التصحيح هنا بصري بحت: نفس البنية
    المنطقية للعناصر (previous/page/next، disabled/active) لكن بالفئتين
    الجاهزتين بدل أي تنسيق مضمَّن.

    تصحيح لاحق (S26 — عطل حقيقي اكتُشف بعد إضافة تبويب "السجلات"): كان هذا
    الملف يستدعي $paginator->elements() مباشرة، وهي دالّة "protected" فعليًا
    في LengthAwarePaginator (راجع مصدر الإطار). استدعاؤها من خارج الصنف (كما
    هنا في Blade) يُشغِّل __call() السحرية في AbstractPaginator التي تُمرِّر
    الاستدعاء لمجموعة العناصر الداخلية (Collection) بدل الصنف نفسه، فتفشل
    بعطل "Method Illuminate\Support\Collection::elements does not exist" —
    لأن Collection لا تملك دالّة بهذا الاسم أصلًا. **لم يظهر هذا العطل من قبل**
    لأن كل صفحة مرقَّمة سابقًا (لوحة الطلاب، سجلّ الطالب) لم تكن تتجاوز صفحة
    واحدة فعليًا في بيانات الاختبار (hasPages() = false يمنع وصول التنفيذ لهذا
    السطر أصلًا) — أوّل صفحة تجاوزت صفحة واحدة فعليًا هي تبويب "السجلات" الجديد
    (306 سجلًّا / 11 صفحة)، فكشفت عطلًا كامنًا موجودًا في هذا الملف منذ S21.
    الإصلاح: استبدال الاستدعاء المحمي بمكافئه العام — Illuminate\Pagination\
    UrlWindow::make($paginator) تُبنى منها نفس مصفوفة "العناصر" حرفيًا (نفس
    منطق LengthAwarePaginator::elements() الداخلي بالضبط، منسوخًا هنا فقط
    باستدعاء علني بدل المحمي) — لا تغيير في الشكل أو السلوك الظاهر للمستخدم،
    إصلاح فنّي بحت.
--}}
@if ($paginator->hasPages())
    @php
        $window = \Illuminate\Pagination\UrlWindow::make($paginator);
        $elements = array_filter([
            $window['first'],
            is_array($window['slider']) ? '...' : null,
            $window['slider'],
            is_array($window['last']) ? '...' : null,
            $window['last'],
        ]);
    @endphp
    <nav class="pagination-nav" aria-label="التنقّل بين الصفحات">
        <ul class="pagination-list">
            @if ($paginator->onFirstPage())
                <li class="disabled"><span>السابق</span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="disabled"><span>{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="active" aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a></li>
            @else
                <li class="disabled"><span>التالي</span></li>
            @endif
        </ul>
    </nav>
@endif
