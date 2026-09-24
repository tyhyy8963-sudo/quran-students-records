<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * المرجع القرآني (S6) — 114 سورة ثابتة، لا تُنشأ ولا تُحذف من التطبيق.
 *
 * منذ S14 يحمل الجدول أيضًا سلّم ترتيب الحفظ المعكوس: رتبة السورة في هذا
 * الترتيب (الناس = 1 … البقرة = 113)، واستثناء الفاتحة كليًا. هذا الترتيب
 * يبقى أساس تحديد "أبعد سورة" في MemorizationProgress، لكن نسبة الحفظ نفسها
 * لم تعد تُحسَب من رتبة السورة مباشرة منذ التصحيح الثاني (موزونة بالأرباع
 * الـ240 بدل عدد السور، راجع MemorizationProgress) — فلا توجد هنا دالة
 * "نسبة السورة نفسها على السلّم" بعد الآن، لأن أي رقم كذلك سيكذب بمجرّد
 * وجوده (لا يطابق النسبة الفعلية المعروضة للطالب).
 */
class Surah extends Model
{
    public $timestamps = true;

    /**
     * عدد السور الداخلة في نسبة الحفظ: 114 ناقص الفاتحة.
     *
     * ثابت مشتقّ من القاعدة لا رقم مكتوب بالصدفة — يُستعمل مقامًا لعدّاد
     * "السور المكتملة" في MemorizationProgress (معلومة عرضية منفصلة عن نسبة
     * الحفظ نفسها)، ويُتحقَّق منه في الاختبارات مقابل الجدول نفسه.
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
}
