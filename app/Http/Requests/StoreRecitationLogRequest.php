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
            'surah_id'    => ['required', 'integer', 'exists:surahs,id'],
            'to_surah_id' => ['nullable', 'integer', 'exists:surahs,id'],
            'from_ayah'   => ['nullable', 'integer', 'min:1'],
            'to_ayah'     => ['required', 'integer', 'min:1'],
            'type'        => ['required', 'string', Rule::in(array_keys(RecitationLog::TYPES))],
            'status'      => ['nullable', 'string', Rule::in(array_keys(RecitationLog::STATUSES))],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'logged_at'   => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'surah_id.required'  => 'اختيار السورة مطلوب.',
            'surah_id.exists'    => 'السورة المختارة غير موجودة.',
            'to_surah_id.exists' => 'سورة النهاية المختارة غير موجودة.',
            'to_ayah.required'   => 'آية النهاية مطلوبة.',
            'to_ayah.min'        => 'رقم الآية يجب أن يكون 1 أو أكثر.',
            'from_ayah.min'      => 'رقم الآية يجب أن يكون 1 أو أكثر.',
            'type.in'            => 'نوع السجلّ غير صحيح.',
            'status.in'          => 'حالة الحفظ غير صحيحة.',
            'notes.max'          => 'الملاحظة طويلة جدًا.',
            'logged_at.before_or_equal' => 'لا يمكن تسجيل تاريخ في المستقبل.',
        ];
    }

    /**
     * مراجعة عابرة لعدّة سور (S16): to_surah_id اختياري، ولو حضر ومختلفًا عن
     * surah_id فهو مسموح فقط لنوع "مراجعة" (الحفظ والتسميع يبقيان بسورة
     * واحدة كما كانا). الترتيب يُفرَض برقم السورة في المصحف (لا بمقارنة رقمي
     * الآية مباشرة، لأنهما على محورين مختلفين حين تختلف السورتان).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $surahId = $this->input('surah_id');
            $toSurahIdInput = $this->input('to_surah_id');
            $from = $this->input('from_ayah');
            $to = $this->input('to_ayah');
            $type = $this->input('type');

            if (! $surahId || ! $to) {
                return;
            }

            $fromSurah = Surah::find($surahId);
            if (! $fromSurah) {
                return;
            }

            $toSurah = $fromSurah;
            if ($toSurahIdInput && (int) $toSurahIdInput !== (int) $surahId) {
                $toSurah = Surah::find($toSurahIdInput);
                if (! $toSurah) {
                    return;
                }

                if ($type !== 'مراجعة') {
                    $validator->errors()->add('to_surah_id', 'المدى عبر أكثر من سورة متاح فقط لنوع "مراجعة".');

                    return;
                }

                // (تصحيح صريح من يحيى): المراجعة قد تسير في أيّ اتجاه — من أول
                // المصحف لآخره أو العكس (الطالب يراجع غالبًا من آخر ما حفظ
                // رجوعًا)، فلا يُفرَض ترتيب مصحفي بين سورتَي البداية والنهاية
                // هنا إطلاقًا. الحفظ (النوع الآخر) لا يصل لهذه النقطة أصلًا —
                // يُرفَض قبلها بشرط ($type !== 'مراجعة') أعلاه، فسلوكه الحالي
                // (بلا مدى متعدّد السور) بقي كما هو دون أي تغيير بهذا التصحيح.
            }

            if ($from && $from > $fromSurah->ayah_count) {
                $validator->errors()->add('from_ayah', "سورة {$fromSurah->name} تحتوي {$fromSurah->ayah_count} آية فقط.");
            }

            if ($to > $toSurah->ayah_count) {
                $validator->errors()->add('to_ayah', "سورة {$toSurah->name} تحتوي {$toSurah->ayah_count} آية فقط.");
            }

            // مقارنة من/إلى مباشرة لا تصحّ إلا داخل نفس السورة — بين سورتين
            // مختلفتين الترتيب مضمون أصلًا برقمي السورة أعلاه.
            if ($toSurah->id === $fromSurah->id && $from && $to && $from > $to) {
                $validator->errors()->add('from_ayah', 'آية البداية يجب أن تسبق آية النهاية.');
            }
        });
    }
}
