@extends('layouts.app')

@section('title', 'حسابات المعلّمين - رِواق')

@section('content')
    <div class="page-title-row">
        <h1 class="mt-0">حسابات المعلّمين</h1>
        <a href="{{ route('admin.teachers.create') }}" class="btn btn-primary">+ حساب معلّم جديد</a>
    </div>

    @if ($errors->any())
        <div class="alert error">{{ $errors->first() }}</div>
    @endif

    {{-- بطاقة بيانات الدخول: تظهر مرّة واحدة فقط بعد الإنشاء أو إعادة التعيين،
         ثم تختفي بانتهاء الجلسة الوامضة. مكانها أعلى الشاشة وبتباين واضح لأنها
         المعلومة الوحيدة في النظام التي لا يمكن استرجاعها لو أُغلقت الصفحة. --}}
    @if ($credentials = session('issued_credentials'))
        <div class="credentials-card" role="status">
            <div class="credentials-head">
                <span class="credentials-icon" aria-hidden="true">🔑</span>
                <div>
                    <h2>
                        {{ $credentials['context'] === 'reset' ? 'تمت إعادة تعيين كلمة المرور' : 'تم إنشاء الحساب' }}
                        — {{ $credentials['name'] }}
                    </h2>
                    <p class="credentials-warning">سلّم هذه البيانات للمعلّم الآن. لن تظهر كلمة المرور مرّة أخرى بعد مغادرة الصفحة.</p>
                </div>
            </div>

            <div class="credentials-grid">
                <div class="credential-field">
                    <span class="credential-label" id="labelUsername">اسم المستخدم</span>
                    <div class="credential-value">
                        <code id="issuedUsername">{{ $credentials['username'] }}</code>
                        <button type="button" class="btn btn-sm btn-ghost copy-btn" data-copy-target="issuedUsername" aria-label="نسخ اسم المستخدم">نسخ</button>
                    </div>
                </div>
                <div class="credential-field">
                    <span class="credential-label" id="labelPassword">كلمة المرور</span>
                    <div class="credential-value">
                        <code id="issuedPassword">{{ $credentials['password'] }}</code>
                        <button type="button" class="btn btn-sm btn-ghost copy-btn" data-copy-target="issuedPassword" aria-label="نسخ كلمة المرور">نسخ</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card card-pad">
        @if ($teachers->isEmpty())
            <div class="empty-state">
                <div class="empty-emoji">👥</div>
                <p>لا حسابات معلّمين بعد.</p>
                <p class="text-muted">أنشئ أول حساب وسلّم بياناته للمعلّم مباشرة — لا يستطيع أحد إنشاء حسابه بنفسه في هذا النظام.</p>
            </div>
        @else
            <div class="accounts-table-head" aria-hidden="true">
                <span>المعلّم</span>
                <span>اسم المستخدم</span>
                <span>الطلاب</span>
                <span>آخر دخول</span>
                <span>الحالة</span>
            </div>

            <div class="accounts-table">
                @foreach ($teachers as $teacher)
                    <div class="account-row {{ $teacher->is_active ? '' : 'is-disabled' }}">
                        <div class="account-cell">
                            <span class="column-label">المعلّم</span>
                            <div>
                                <span class="account-name">{{ $teacher->name }}</span>
                                @if ($teacher->mosque || $teacher->classroom)
                                    <span class="account-meta">{{ $teacher->mosque }}@if ($teacher->mosque && $teacher->classroom) — @endif{{ $teacher->classroom }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="account-cell">
                            <span class="column-label">اسم المستخدم</span>
                            <code class="username-chip">{{ $teacher->username }}</code>
                        </div>

                        <div class="account-cell">
                            <span class="column-label">الطلاب</span>
                            <span>{{ $teacher->students_count }}</span>
                        </div>

                        <div class="account-cell">
                            <span class="column-label">آخر دخول</span>
                            <span class="{{ $teacher->last_login_at ? '' : 'text-muted' }}">
                                {{ $teacher->last_login_at ? $teacher->last_login_at->diffForHumans() : 'لم يدخل بعد' }}
                            </span>
                        </div>

                        <div class="account-cell">
                            <span class="column-label">الحالة</span>
                            <span class="badge {{ $teacher->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $teacher->is_active ? 'نشط' : 'معطَّل' }}
                            </span>
                        </div>

                        <div class="account-cell account-actions">
                            {{-- عرض سجلّات المعلّم الكاملة (S20) — قراءة فقط، منفصل عن
                                 أزرار إدارة الحساب التالية. --}}
                            <a href="{{ route('admin.teachers.report', $teacher) }}" class="btn btn-sm btn-secondary">عرض السجلّ</a>

                            {{-- تعديل اسم المستخدم (طلب صريح من يحيى: "أبغى التعديل يشمل
                                 اسم المستخدم كمان" — كان التعديل من هذه الشاشة يقتصر على
                                 كلمة المرور فقط). نفس نمط "تخصيص كلمة مرور" أدناه: تفاصيل
                                 قابلة للطيّ بدل نموذج ظاهر دومًا لكل صفّ. --}}
                            <details class="inline-form">
                                <summary class="btn btn-sm btn-ghost">تعديل اسم المستخدم</summary>
                                <form method="POST" action="{{ route('admin.teachers.username', $teacher) }}" class="js-confirm inline-password-form"
                                      data-confirm="تغيير اسم مستخدم {{ $teacher->name }}؟ سيحتاج استعمال الاسم الجديد من دخوله القادم.">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" class="input" name="username" required minlength="3" maxlength="50"
                                           pattern="[a-zA-Z0-9_]+" value="{{ $teacher->username }}"
                                           placeholder="اسم المستخدم الجديد" aria-label="اسم مستخدم جديد لـ{{ $teacher->name }}">
                                    <button type="submit" class="btn btn-sm btn-primary">حفظ</button>
                                </form>
                            </details>

                            <form method="POST" action="{{ route('admin.teachers.password', $teacher) }}" class="inline-form js-confirm"
                                  data-confirm="توليد كلمة مرور عشوائية جديدة لـ{{ $teacher->name }}؟ ستتوقّف كلمته الحالية فورًا.">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-secondary">توليد تلقائي</button>
                            </form>

                            {{-- تخصيص كلمة المرور يدويًا (بعد طلب صاحب المنظومة) — الخادم
                                 يقبل حقل password منذ البداية، الفجوة كانت في الواجهة فقط:
                                 لا حقل إدخال كان معروضًا أصلًا، فيسقط دائمًا على التوليد
                                 التلقائي. --}}
                            <details class="inline-form">
                                <summary class="btn btn-sm btn-ghost">تخصيص كلمة مرور</summary>
                                <form method="POST" action="{{ route('admin.teachers.password', $teacher) }}" class="js-confirm inline-password-form"
                                      data-confirm="تعيين كلمة المرور المكتوبة لـ{{ $teacher->name }}؟ ستتوقّف كلمته الحالية فورًا.">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" class="input" name="password" required minlength="6" maxlength="100"
                                           placeholder="كلمة المرور الجديدة (٦ رموز فأكثر)" aria-label="كلمة مرور مخصّصة لـ{{ $teacher->name }}">
                                    <button type="submit" class="btn btn-sm btn-primary">تعيين</button>
                                </form>
                            </details>

                            <form method="POST" action="{{ route('admin.teachers.active', $teacher) }}" class="inline-form js-confirm"
                                  data-confirm="{{ $teacher->is_active ? 'تعطيل حساب '.$teacher->name.'؟ لن يتمكّن من الدخول، وتبقى سجلّات طلابه كما هي.' : 'إعادة تفعيل حساب '.$teacher->name.'؟' }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-ghost">{{ $teacher->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                            </form>

                            @if ($teacher->students_count === 0)
                                <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}" class="inline-form js-confirm"
                                      data-confirm="حذف حساب {{ $teacher->name }} نهائيًا؟">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-ghost btn-danger-ghost">حذف</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card card-pad" style="margin-top: var(--space-5);">
        <h2 class="mt-0">حسابات الإدارة</h2>
        <p class="text-muted" style="margin-bottom: var(--space-4);">
            لا تُدار حسابات المديرين من هذه الشاشة. لإضافة مدير أو استرجاع دخوله تُستخدم أوامر الخادم
            (<code>keshf:create-admin</code> و<code>keshf:reset-password</code>)، ولا يمكن حذف آخر مدير نشط أو تعطيله.
        </p>

        <div class="accounts-table">
            @foreach ($admins as $admin)
                <div class="account-row">
                    <div class="account-cell">
                        <span class="column-label">المدير</span>
                        <span class="account-name">{{ $admin->name }}</span>
                    </div>
                    <div class="account-cell">
                        <span class="column-label">اسم المستخدم</span>
                        <code class="username-chip">{{ $admin->username }}</code>
                    </div>
                    <div class="account-cell">
                        <span class="column-label">آخر دخول</span>
                        <span class="{{ $admin->last_login_at ? '' : 'text-muted' }}">
                            {{ $admin->last_login_at ? $admin->last_login_at->diffForHumans() : 'لم يدخل بعد' }}
                        </span>
                    </div>
                    <div class="account-cell">
                        <span class="badge badge-graduated">مدير</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const { confirmDialog, toast } = window.KeshfApp;

    document.querySelectorAll('form.js-confirm').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === 'yes') return;
            e.preventDefault();

            if (await confirmDialog(form.dataset.confirm)) {
                form.dataset.confirmed = 'yes';
                form.submit();
            }
        });
    });

    document.querySelectorAll('.copy-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const text = document.getElementById(btn.dataset.copyTarget)?.textContent?.trim() ?? '';
            try {
                await navigator.clipboard.writeText(text);
                toast('تم النسخ.', 'success');
            } catch (e) {
                // نسخ الحافظة يتطلّب سياقًا آمنًا (HTTPS أو localhost)؛ على شبكة
                // محلية بـHTTP يفشل صامتًا — فنُظهر البديل بدل ترك الزر بلا أثر.
                toast('تعذّر النسخ تلقائيًا — حدّد النص وانسخه يدويًا.', 'error');
            }
        });
    });
});
</script>
@endpush
