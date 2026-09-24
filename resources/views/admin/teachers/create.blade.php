@extends('layouts.app')

@section('title', 'حساب معلّم جديد - رِواق')

@section('content')
    <div class="page-title-row">
        <a href="{{ route('admin.teachers.index') }}" class="btn btn-sm btn-ghost">→ العودة للحسابات</a>
    </div>

    <div class="card card-pad auth-card" style="margin-inline: auto;">
        <h1 class="mt-0">حساب معلّم جديد</h1>
        <p class="subtitle">أنشئ الحساب هنا، ثم سلّم اسم المستخدم وكلمة المرور للمعلّم مباشرة.</p>

        <form method="POST" action="{{ route('admin.teachers.store') }}">
            @csrf

            <div class="field">
                <label class="field-label" for="name">اسم المعلّم</label>
                <input class="input @error('name') has-error @enderror" type="text" id="name" name="name"
                       value="{{ old('name') }}" placeholder="عبدالرحمن الأحمد" required autofocus>
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="field-label" for="username">اسم المستخدم للدخول</label>
                <input class="input @error('username') has-error @enderror" type="text" id="username" name="username"
                       value="{{ old('username') }}" placeholder="abdulrahman" required
                       dir="ltr" style="text-align: left;" autocomplete="off"
                       aria-describedby="usernameHint">
                <span class="field-hint" id="usernameHint">حروف لاتينية وأرقام وشرطة سفلية فقط — يكتبه المعلّم في كل مرة يدخل فيها.</span>
                @error('username')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label class="field-label" for="mosque">الجامع <span class="text-muted">(اختياري)</span></label>
                <input class="input" type="text" id="mosque" name="mosque" value="{{ old('mosque') }}" placeholder="جامع الفرقان">
            </div>

            <div class="field">
                <label class="field-label" for="classroom">الحلقة <span class="text-muted">(اختياري)</span></label>
                <input class="input" type="text" id="classroom" name="classroom" value="{{ old('classroom') }}" placeholder="حلقة الفجر">
            </div>

            <div class="field">
                <label class="field-label" for="password">كلمة المرور <span class="text-muted">(اتركها فارغة ليولّدها النظام)</span></label>
                <input class="input @error('password') has-error @enderror" type="text" id="password" name="password"
                       dir="ltr" style="text-align: left;" autocomplete="off"
                       placeholder="تُولَّد تلقائيًا" aria-describedby="passwordHint">
                <span class="field-hint" id="passwordHint">كلمة المرور المولَّدة خالية من الرموز المتشابهة عند الإملاء (0/O و1/l).</span>
                @error('password')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block">إنشاء الحساب</button>
        </form>
    </div>
@endsection
