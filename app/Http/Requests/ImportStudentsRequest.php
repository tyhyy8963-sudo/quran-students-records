<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * استيراد طلاب من ملف CSV (S11).
 */
class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'اختر ملفًا أولًا.',
            'file.mimes'    => 'الملف يجب أن يكون بصيغة CSV.',
            'file.max'      => 'حجم الملف كبير جدًا (الحد الأقصى 1 ميجابايت).',
        ];
    }
}
