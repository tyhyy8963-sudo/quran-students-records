@extends('layouts.app')

@section('title', 'الحلقات - رِواق')

{{--
    صفحة "الحلقات" (طلب صريح من يحيى 2026-09-23: "ضيف تبويب الحلقات في
    الأعلى ... وأحذفه من صفحة القرآن وضبط الصفحة حقت إدارة الحلقات") — كانت
    هذه الإدارة (إضافة/حذف حلقة) محصورة في نافذة منبثقة (#circlesModalBackdrop)
    داخل dashboard.blade.php منذ S24 (الجزء الثالث). صارت الآن صفحة مستقلّة
    كاملة بتبويبها الخاصّ في الشريط العلوي، بدل الزرّ "⚙ إدارة الحلقات" الذي
    أُزيل من لوحة القرآن.

    بنية #circleChips و#addCircleForm الداخلية، ومعالجا الإضافة/الحذف في
    JS أسفل الصفحة، منقولان بلا أي تغيير منطقي عن النافذة المنبثقة القديمة —
    فقط الغلاف الظاهر تغيّر من modal-backdrop/modal إلى صندوق صفحة عادي
    (.circles-panel، نمط تصميم كان موجودًا في app.css أصلًا من عهد الحلقات
    الأول (S6) قبل انتقالها لاحقًا إلى النافذة المنبثقة، ولم يكن يُستعمَل من
    أي قالب — يُستعمَل هنا أخيرًا في مكانه الصحيح).
--}}
@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">الحلقات</h1>
    </div>

    <p class="text-muted">
        أضف حلقاتك هنا لتُستعمَل في تصنيف وفلترة الطلاب عبر تبويبات "قرآن"
        و"المتون" و"السجلات". حذف حلقة لا يحذف طلابها — يُعيدهم إلى "بلا حلقة".
    </p>

    <div class="circles-panel">
        <div class="cluster" id="circleChips">
            @forelse ($circles as $circle)
                <span class="circle-chip" data-id="{{ $circle->id }}">
                    {{ $circle->name }}
                    <button type="button" class="delete-circle" title="حذف الحلقة" aria-label="حذف الحلقة {{ $circle->name }}"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></button>
                </span>
            @empty
                <span class="text-muted" id="noCirclesYet">لا حلقات بعد.</span>
            @endforelse
        </div>
        <form class="add-circle" id="addCircleForm">
            <label class="sr-only" for="newCircleName">اسم حلقة جديدة</label>
            <input class="input" id="newCircleName" placeholder="اسم حلقة جديدة" maxlength="255">
            <button type="submit" class="btn btn-sm btn-secondary">+ إضافة حلقة</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetch, toast, confirmDialog } = window.KeshfApp;

    const circleChips = document.getElementById('circleChips');
    const addCircleForm = document.getElementById('addCircleForm');

    addCircleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('newCircleName');
        const name = input.value.trim();
        if (!name) return;

        try {
            await apiFetch('/circles', { method: 'POST', body: { name } });
            toast('تمت إضافة الحلقة.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status !== 419) toast(error.message, 'error');
        }
    });

    circleChips.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-circle');
        if (!btn) return;
        const chip = btn.closest('.circle-chip');
        const confirmed = await confirmDialog('حذف هذه الحلقة؟ طلابها يبقون بلا حذف.');
        if (!confirmed) return;

        try {
            await apiFetch(`/circles/${chip.dataset.id}`, { method: 'DELETE' });
            toast('تم حذف الحلقة.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status !== 419) toast(error.message, 'error');
        }
    });
});
</script>
@endpush
