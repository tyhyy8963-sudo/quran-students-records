<?php

namespace App\Http\Requests;

use App\Models\RecitationLog;
use App\Models\Surah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecitationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التفويض الفعلي في RecitationLogController عبر ملكية الطالب
        // (نفس معلّم الطلب)، إذ الموديل المستهدَف (الطالب) معروف من المسار.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'surah_id'  => ['required', 'integer', 'exists:surahs,id'],
            'from_ayah' => ['nullable', 'integer', 'min:1'],
            'to_ayah'   => ['required', 'integer', 'min:1'],
            'type'      => ['required', 'string', Rule::in(array_keys(RecitationLog::TYPES))],
            'grade'     => ['nullable', 'string', Rule::in(array_keys(RecitationLog::GRADES))],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'logged_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'surah_id.required'  => 'اختيار السورة مطلوب.',
            'surah_id.exists'    => 'السورة المختارة غير موجودة.',
            'to_ayah.required'   => 'آية النهاية مطلوبة.',
            'to_ayah.min'        => 'رقم الآية يجب أن يكون 1 أو أكثر.',
            'from_ayah.min'      => 'رقم الآية يجب أن يكون 1 أو أكثر.',
            'type.in'            => 'نوع السجلّ غير صحيح.',
            'grade.in'           => 'التقييم غير صحيح.',
            'notes.max'          => 'الملاحظة طويلة جدًا.',
            'logged_at.before_or_equal' => 'لا يمكن تسجيل تاريخ في المستقبل.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $surahId = $this->input('surah_id');
            $from = $this->input('from_ayah');
            $to = $this->input('to_ayah');

            if (! $surahId || ! $to) {
                return;
            }

            $surah = Surah::find($surahId);
            if (! $surah) {
                return;
            }

            if ($to > $surah->ayah_count) {
                $validator->errors()->add('to_ayah', "سورة {$surah->name} تحتوي {$surah->ayah_count} آية فقط.");
            }

            if ($from && $to && $from > $to) {
                $validator->errors()->add('from_ayah', 'آية البداية يجب أن تسبق آية النهاية.');
            }
        });
    }
}
