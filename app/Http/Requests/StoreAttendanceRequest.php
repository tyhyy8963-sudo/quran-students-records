<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * حفظ دفعة حضور ليوم واحد (S9) — الشاشة السريعة ترسل كل الطلاب المحدَّدين
 * في نداء واحد بدل نداء لكل طالب.
 */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'student_id')->where('teacher_id', $this->user()?->id),
            ],
            'entries.*.status' => ['required', Rule::in(array_keys(Attendance::STATUSES))],
            'entries.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'لا يمكن تسجيل حضور في المستقبل.',
            'entries.required' => 'اختر حالة حضور واحدة على الأقل.',
            'entries.*.student_id.exists' => 'أحد الطلاب المحدَّدين غير موجود ضمن طلابك.',
        ];
    }
}
