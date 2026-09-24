<?php

namespace App\Http\Requests;

use App\Models\Poem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * ضبط/تعديل أرضية متن لطالب (S15) — "آخر بيت أتمّه من هذا المتن قبل
 * الانضمام". upsert لا إنشاء متكرّر: راجع PoemBaselineController.
 */
class StorePoemBaselineRequest extends FormRequest
{
    public function authorize(): bool
    {
        // التفويض الفعلي عبر StudentPolicy@update في PoemBaselineController،
        // إذ يحتاج القرار إلى الطالب المستهدَف لا الطلب وحده.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'baseline_bayt' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'baseline_bayt.required' => 'رقم البيت مطلوب.',
            'baseline_bayt.integer'  => 'رقم البيت يجب أن يكون رقمًا صحيحًا.',
            'baseline_bayt.min'      => 'رقم البيت يجب أن يكون 1 أو أكثر.',
        ];
    }

    /** حدّ البيت يعتمد على المتن المستهدَف في المسار — لا يُعبَّر عنه بقاعدة ثابتة. */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $poem = Poem::find($this->route('poem'));
            $bayt = $this->input('baseline_bayt');

            if ($poem && $bayt && $bayt > $poem->bayt_count) {
                $validator->errors()->add(
                    'baseline_bayt',
                    "متن {$poem->name} يحتوي {$poem->bayt_count} بيتًا فقط."
                );
            }
        });
    }
}
