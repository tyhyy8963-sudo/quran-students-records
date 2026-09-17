<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * المرجع القرآني (S6) — 114 سورة ثابتة، لا تُنشأ ولا تُحذف من التطبيق.
 *
 * منذ S14 يحمل الجدول أيضًا سلّم قياس التقدّم: رتبة السورة في ترتيب الحفظ
 * المعكوس (الناس = 1 … البقرة = 113)، واستثناء الفاتحة من العدّ.
 */
class Surah extends Model
{
    public $timestamps = true;

    /**
     * عدد السور الداخلة في نسبة الحفظ: 114 ناقص الفاتحة.
     *
     * ثابت مشتقّ من القاعدة لا رقم مكتوب بالصدفة — يُستعمل مقامًا للنسبة في
     * MemorizationProgress، ويُتحقَّق منه في الاختبارات مقابل الجدول نفسه.
     */
    public const COUNTABLE_COUNT = 113;

    protected $fillable = [
        'number', 'name', 'ayah_count', 'memorization_order', 'excluded_from_progress',
    ];

    protected $casts = [
        'number'                 => 'integer',
        'ayah_count'             => 'integer',
        'memorization_order'     => 'integer',
        'excluded_from_progress' => 'boolean',
    ];

    public function recitationLogs()
    {
        return $this->hasMany(RecitationLog::class);
    }

    /** السور الداخلة في حساب النسبة (كل شيء عدا الفاتحة). */
    public function scopeCountable(Builder $query): Builder
    {
        return $query->where('excluded_from_progress', false);
    }

    /** ترتيب العرض كما يُحفظ فعلًا: من الناس صعودًا نحو البقرة. */
    public function scopeInMemorizationOrder(Builder $query): Builder
    {
        return $query->orderByRaw('memorization_order IS NULL')->orderBy('memorization_order');
    }

    /**
     * موضع السورة على سلّم النسبة: الناس ≈ 1% والبقرة = 100%.
     *
     * هذه نسبة السورة نفسها (أين تقع في الطريق)، لا نسبة الطالب — نسبة الطالب
     * تُحسب في MemorizationProgress من مجموع ما أتمّه فعلًا.
     */
    public function progressPercent(): float
    {
        if ($this->excluded_from_progress || $this->memorization_order === null) {
            return 0.0;
        }

        return round($this->memorization_order / self::COUNTABLE_COUNT * 100, 1);
    }
}
