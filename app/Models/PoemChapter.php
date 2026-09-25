<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * باب ثابت من أبواب متن (S39 — بطلب يحيى، بناءً على مستند "أبواب المتون
 * الستة" الذي أرسله): مرجع بحت مثل Poem نفسه — يُزرَع فقط عبر migration
 * (راجع 2026_09_25_000001_add_poem_chapters_and_sixth_poem)، لا إضافة ولا
 * تعديل من الواجهة.
 *
 * from_bayt..to_bayt مدى ثابت غير متداخل مع بقية أبواب نفس المتن (متحقَّق
 * آليًا وقت الزرع) — "باب الطالب الحالي" لمتن هو الباب الذي يقع فيه آخر بيت
 * وصل إليه (راجع Poem::chapterAt()).
 */
class PoemChapter extends Model
{
    protected $fillable = [
        'poem_id', 'name', 'from_bayt', 'to_bayt',
    ];

    protected $casts = [
        'from_bayt' => 'integer',
        'to_bayt'   => 'integer',
    ];

    public function poem()
    {
        return $this->belongsTo(Poem::class);
    }
}
