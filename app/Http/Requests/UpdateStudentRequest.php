<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التفويض الفعلي عبر StudentPolicy@update في StudentController،
        // إذ يحتاج القرار إلى الطالب المستهدَف لا الطلب وحده.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:255'],
            'circle_id'    => ['nullable', 'integer', Rule::exists('circles', 'id')->where('teacher_id', $this->user()?->id)],
            'status'       => ['sometimes', 'string', 'in:active,inactive,transferred,graduated'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_name.required' => 'اسم الطالب مطلوب.',
            'student_name.max'      => 'اسم الطالب طويل جدًا.',
            'circle_id.exists'      => 'الحلقة المختارة غير موجودة.',
            'status.in'             => 'حالة الطالب غير صحيحة.',
        ];
    }
}
