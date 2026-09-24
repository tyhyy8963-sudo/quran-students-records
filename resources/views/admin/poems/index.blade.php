@extends('layouts.app')

@section('title', 'المتون - رِواق')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">مرجع المتون</h1>
    </div>

    <div class="card card-pad">
        @if ($poems->isEmpty())
            <div class="empty-state">
                <div class="empty-emoji">📜</div>
                <p>لا متون بعد.</p>
            </div>
        @else
            <div class="accounts-table-head" aria-hidden="true">
                <span>المتن</span>
                <span>عدد الأبيات</span>
            </div>

            <div class="accounts-table">
                @foreach ($poems as $poem)
                    <div class="account-row">
                        <div class="account-cell">
                            <span class="column-label">المتن</span>
                            <span class="account-name">{{ $poem->name }}</span>
                        </div>
                        <div class="account-cell">
                            <span class="column-label">عدد الأبيات</span>
                            <span>{{ $poem->bayt_count }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">إضافة متن جديد</h2>
        <form id="addPoemForm">
            @csrf
            <div class="form-grid-2">
                <div class="field">
                    <label class="field-label" for="poem_name">اسم المتن</label>
                    <input class="input" type="text" id="poem_name" name="name" maxlength="255" required>
                    <span class="field-error" data-for="name"></span>
                </div>
                <div class="field">
                    <label class="field-label" for="poem_bayt_count">عدد الأبيات</label>
                    <input class="input" type="number" min="1" id="poem_bayt_count" name="bayt_count" required>
                    <span class="field-error" data-for="bayt_count"></span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="addPoemBtn">إضافة المتن</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { apiFetch, toast, withButtonLoading, applyFieldErrors } = window.KeshfApp;

    const form = document.getElementById('addPoemForm');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(new FormData(form).entries());

        const btn = document.getElementById('addPoemBtn');
        try {
            await withButtonLoading(btn, () => apiFetch('/admin/poems', { method: 'POST', body: payload }));
            toast('تمت إضافة المتن بنجاح.', 'success');
            window.location.reload();
        } catch (error) {
            if (error.status === 422 && error.errors) {
                applyFieldErrors(form, error.errors);
            } else if (error.status !== 419) {
                toast(error.message, 'error');
            }
        }
    });
});
</script>
@endpush
