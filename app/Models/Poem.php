<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * متن حفظ ثابت (S15) — مرجع يُضاف إليه من لوحة المدير فقط (Admin\PoemController)،
 * لا حذف ولا تعديل من الواجهة.
 */
class Poem extends Model
{
    protected $fillable = [
        'name', 'bayt_count',
    ];

    protected $casts = [
        'bayt_count' => 'integer',
    ];

    public function poemRecitationLogs()
    {
        return $this->hasMany(PoemRecitationLog::class);
    }

    /**
     * أبواب المتن الثابتة مرتَّبة بترتيب أبياتها (S39) — راجع تعليق
     * PoemChapter::class.
     */
    public function chapters()
    {
        return $this->hasMany(PoemChapter::class)->orderBy('from_bayt');
    }

    /**
     * الباب الذي يقع فيه بيت معيّن — أساس "الباب الحالي" الذي وصل إليه
     * الطالب (S39، بطلب يحيى). قد تُعيد null إن كان البيت خارج كل الأبواب
     * المزروعة (حاليًا فقط طيبة النشر، التي لا يغطّي فرز أبوابها في المستند
     * كامل أبياتها — راجع تعليق migration الزرع) أو إن كان $bayt <= 0.
     */
    public function chapterAt(int $bayt): ?PoemChapter
    {
        if ($bayt < 1) {
            return null;
        }

        return $this->chapters()
            ->where('from_bayt', '<=', $bayt)
            ->where('to_bayt', '>=', $bayt)
            ->first();
    }
}
