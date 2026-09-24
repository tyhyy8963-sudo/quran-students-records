{{--
    نموذج تسجيل مصغّر لسطر واحد (درس أو مراجعة) داخل صف طالب بالقائمة
    الموحّدة (S23) — نفس حقول نموذج "إضافة سجلّ جديد" في صفحة الطالب
    (resources/views/students/show.blade.php) لكن بلا حقل "النوع" (مُحدَّد
    مسبقًا هنا حسب أي عمود يظهر فيه النموذج) وبقيم مُعبَّأة تلقائيًا من آخر
    موضع مسجَّل ($next، بند 2/3 من تقرير التطوير: "حفظ آخر بيانات... فلا يعيد
    المعلّم إدخالها من الصفر").

    props:
    - student: App\Models\Student
    - type: 'حفظ' | 'مراجعة'
    - next: array{surah_id:int, ayah:int}|null — موضع الاستكمال المقترَح
    - surahs: Illuminate\Support\Collection<int, App\Models\Surah>

    جزئية عادية (@include) لا مكوّن Blade: تُستدعى مرّتين لكل صف طالب (درس
    ومراجعة) بمتغيّرات مختلفة، و@include أبسط لتمرير $surahs (كبيرة، مشتركة
    بين كل الصفوف) بلا إعادة تمريرها كخاصية مكوّن في كل نداء.
--}}

<form class="mini-log-form" data-type="{{ $type }}" data-student-id="{{ $student->student_id }}">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <select class="input input-sm log-surah" name="surah_id" required aria-label="سورة {{ $type }} — {{ $student->student_name }}">
        <option value="">— السورة —</option>
        @foreach ($surahs as $surah)
            <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}"
                @selected($next && (int) $next['surah_id'] === $surah->id)>
                {{ $surah->name }}
            </option>
        @endforeach
    </select>
    <input class="input input-sm log-from-ayah" type="number" min="1" name="from_ayah" placeholder="من آية"
           value="{{ $next['ayah'] ?? '' }}" aria-label="من آية">
    @if ($type === 'مراجعة')
        <select class="input input-sm log-to-surah" name="to_surah_id" aria-label="إلى سورة (اختياري)">
            <option value="">— نفس السورة —</option>
            @foreach ($surahs as $surah)
                <option value="{{ $surah->id }}" data-ayah-count="{{ $surah->ayah_count }}">{{ $surah->name }}</option>
            @endforeach
        </select>
    @endif
    <input class="input input-sm log-to-ayah" type="number" min="1" name="to_ayah" placeholder="إلى آية" required aria-label="إلى آية">
    <select class="input input-sm log-status" name="status" aria-label="حالة الحفظ (اختياري)">
        <option value="">—</option>
        @foreach (\App\Models\RecitationLog::STATUSES as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-sm btn-primary">حفظ</button>
    <span class="field-error mini-log-error"></span>
</form>
