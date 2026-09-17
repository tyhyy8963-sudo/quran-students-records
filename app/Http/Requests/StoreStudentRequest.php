<?php

namespace App\Http\Requests;

use App\Models\Surah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // كل مستخدم مسجّل دخوله يستطيع إضافة طالب لنفسه — لا كائن قائم
        // بعد للتحقق منه هنا، فالتفويض الفعلي في TeacherScope عند القراءة
        // وفي StudentPolicy عند التعديل والحذف.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:255'],
            'circle_id'    => ['nullable', 'integer', Rule::exists('circles', 'id')->where('teacher_id', $this->user()?->id)],
            'surah_id'     => ['nullable', 'integer', 'exists:surahs,id'],
            'the_ayah'     => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_name.required' => 'اسم الطالب مطلوب.',
            'student_name.max'      => 'اسم الطالب طويل جدًا.',
            'circle_id.exists'      => 'الحلقة المختارة غير موجودة.',
            'surah_id.exists'       => 'السورة المختارة غير موجودة.',
            'the_ayah.integer'      => 'رقم الآية يجب أن يكون رقمًا صحيحًا.',
            'the_ayah.min'          => 'رقم الآية يجب أن يكون 1 أو أكثر.',
        ];
    }

    /**
     * حدّ الآية يعتمد على السورة المختارة (S6) — لا يمكن التعبير عنه بقاعدة
     * ثابتة في rules()، فيُفحص هنا بعد نجاح القواعد الأساسية.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $surahId = $this->input('surah_id');
            $ayah = $this->input('the_ayah');

            if ($surahId && $ayah) {
                $surah = Surah::find($surahId);
                if ($surah && $ayah > $surah->ayah_count) {
                    $validator->errors()->add(
                        'the_ayah',
                        "سورة {$surah->name} تحتوي {$surah->ayah_count} آية فقط."
                    );
                }
            }
        });
    }
}
