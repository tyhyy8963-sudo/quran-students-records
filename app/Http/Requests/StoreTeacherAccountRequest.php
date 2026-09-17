<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء حساب معلّم من لوحة المدير (S13).
 *
 * كلمة المرور اختيارية عمدًا: تُترك فارغة فيولّدها النظام (الحالة الغالبة)،
 * أو يكتبها المدير بنفسه لو فضّل كلمة يسهل إملاؤها على معلّم بعينه.
 */
class StoreTeacherAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            // اسم المستخدم يُكتب بالمحارف اللاتينية والأرقام والشرطة السفلية فقط:
            // يُملى بالهاتف ويُكتب في حقل دخول، والعربية هنا تفتح باب تشابه
            // بصري (أ/ا) وتبديل لوحة المفاتيح عند كل تسجيل دخول.
            'username'  => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users', 'username')],
            'mosque'    => ['nullable', 'string', 'max:255'],
            'classroom' => ['nullable', 'string', 'max:255'],
            'password'  => ['nullable', 'string', 'min:6', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'الرجاء إدخال اسم المعلّم.',
            'username.required' => 'الرجاء إدخال اسم المستخدم.',
            'username.min'      => 'اسم المستخدم يجب أن يكون 3 محارف أو أكثر.',
            'username.regex'    => 'اسم المستخدم يقبل الحروف اللاتينية والأرقام والشرطة السفلية فقط.',
            'username.unique'   => 'اسم المستخدم مستخدم بالفعل — اختر غيره.',
            'password.min'      => 'كلمة المرور يجب أن تكون 6 رموز أو أكثر.',
        ];
    }
}
