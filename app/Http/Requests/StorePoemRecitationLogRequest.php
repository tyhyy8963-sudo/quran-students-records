<?php

namespace App\Http\Requests;

use App\Models\Poem;
use App\Models\RecitationLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePoemRecitationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التفويض الفعلي في PoemRecitationLogController عبر ملكية الطالب
        // (نفس معلّم الطلب)، إذ الموديل المستهدَف (الطالب) معروف من المسار.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'poem_id'   => ['required', 'integer', 'exists:poems,id'],
            'from_bayt' => ['nullable', 'integer', 'min:1'],
            'to_bayt'   => ['required', 'integer', 'min:1'],
            'type'      => ['required', 'string', Rule::in(array_keys(RecitationLog::TYPES))],
            'status'    => ['nullable', 'string', Rule::in(array_keys(RecitationLog::STATUSES))],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'logged_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'poem_id.required'   => 'اختيار المتن مطلوب.',
            'poem_id.exists'     => 'المتن المختار غير موجود.',
            'to_bayt.required'   => 'بيت النهاية مطلوب.',
            'to_bayt.min'        => 'رقم البيت يجب أن يكون 1 أو أكثر.',
            'from_bayt.min'      => 'رقم البيت يجب أن يكون 1 أو أكثر.',
            'type.in'            => 'نوع السجلّ غير صحيح.',
            'status.in'          => 'حالة الحفظ غير صحيحة.',
            'notes.max'          => 'الملاحظة طويلة جدًا.',
            'logged_at.before_or_equal' => 'لا يمكن تسجيل تاريخ في المستقبل.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $poemId = $this->input('poem_id');
            $from = $this->input('from_bayt');
            $to = $this->input('to_bayt');

            if (! $poemId || ! $to) {
                return;
            }

            $poem = Poem::find($poemId);
            if (! $poem) {
                return;
            }

            if ($to > $poem->bayt_count) {
                $validator->errors()->add('to_bayt', "متن {$poem->name} يحتوي {$poem->bayt_count} بيتًا فقط.");
            }

            if ($from && $to && $from > $to) {
                $validator->errors()->add('from_bayt', 'بيت البداية يجب أن يسبق بيت النهاية.');
            }
        });
    }
}
