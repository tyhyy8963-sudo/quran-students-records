<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إضافة متن جديد إلى المرجع — من لوحة المدير فقط (S15).
 */
class StorePoemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255', 'unique:poems,name'],
            'bayt_count' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'اسم المتن مطلوب.',
            'name.unique'         => 'هذا المتن مضاف مسبقًا.',
            'bayt_count.required' => 'عدد الأبيات مطلوب.',
            'bayt_count.integer'  => 'عدد الأبيات يجب أن يكون رقمًا صحيحًا.',
            'bayt_count.min'      => 'عدد الأبيات يجب أن يكون 1 أو أكثر.',
        ];
    }
}
