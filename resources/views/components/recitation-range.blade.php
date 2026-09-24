{{--
    نطاق سجلّ حفظ/مراجعة واحد كبطاقة مقسومة "من/إلى" (تصحيح بصري لاحق —
    اللوحة الرئيسية الموحّدة S23، بطلب صريح من يحيى بعد إرساله صورًا مرجعية
    لشكل الصفّ يريده حرفيًا): جزءان جنبًا إلى جنب يفصلهما خطّ رفيع، كل جزء
    يعرض "السورة – رقم الآية" وتسمية صغيرة تحته ("من"/"إلى") — بدل الصيغة
    النصّية المتّصلة القديمة ("السورة — آية X إلى Y") التي كانت هنا قبل هذا
    التصحيح. عند غياب from_ayah (حقل اختياري في نموذج التسجيل) لا يوجد طرف
    "من" فعلي لعرضه، فتُستعمَل صيغة نصّية بديلة بسطر واحد ("حتى آية Y") —
    حالة لم ترد في الصور المرجعية، فهذا افتراض تقني معقول لا قرار معماري.

    $log: App\Models\RecitationLog (أو App\Models\PoemRecitationLog مع
    from_bayt/to_bayt بدل from_ayah/to_ayah — يُستعمل هنا للقرآن فقط حاليًا).
--}}
@props(['log'])

@php
    $fromAyah = $log->from_ayah;
    $fromSurahName = $log->surah->name ?? '—';
    $toSurahName = $log->spansMultipleSurahs() ? ($log->toSurah->name ?? '—') : $fromSurahName;
@endphp

@if ($fromAyah)
    <span {{ $attributes->merge(['class' => 'range-chip-parts']) }}>
        <span class="range-chip-part">
            <span class="range-chip-value">{{ $fromSurahName }} – {{ $fromAyah }}</span>
            <span class="range-chip-caption">من</span>
        </span>
        <span class="range-chip-divider"></span>
        <span class="range-chip-part">
            <span class="range-chip-value">{{ $toSurahName }} – {{ $log->to_ayah }}</span>
            <span class="range-chip-caption">إلى</span>
        </span>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'range-chip-single']) }}>{{ $fromSurahName }} — حتى آية {{ $log->to_ayah }}</span>
@endif
