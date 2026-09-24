<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سطر واحد في السجلّ الزمني لطالب (S7).
 *
 * (تصحيح صريح من يحيى، أيقونة تعديل ✎ بدل ✕ التراجع فقط): يظهر للمستخدم
 * الآن كـ"تعديل" مباشر لسجلّ اليوم عبر RecitationLogController::update() —
 * لكنه يبقى فنيًا حذفًا للسطر القديم وإنشاء سطر جديد مكانه (لا UPDATE على
 * عمود قائم)، فلا يُنشأ أي عمود "عُدِّل بتاريخ" وتبقى كل سطر كما كان دومًا:
 * يُنشأ مرّة ويُحذف فقط، لا يتغيّر بعد إنشائه مباشرة.
 *
 * منذ S16: مدى السجلّ قد يمتدّ عبر أكثر من سورة (مراجعة حزب مثلًا لا تقف عند
 * حدود سورة). surah_id يبقى "من سورة" كما كان دائمًا؛ to_surah_id اختياري —
 * NULL يعني ما كان يعنيه دائمًا: سجلّ محصور بسورة واحدة. لا يُملأ إلا لنوع
 * "مراجعة" (راجع StoreRecitationLogRequest::withValidator()).
 */
class RecitationLog extends Model
{
    /**
     * (S22) "تسميع" أُلغيت نهائيًا بطلب صريح من يحيى — لم يعد لها فائدة
     * فعلية كقسم مستقلّ عن حفظ/مراجعة. سجلّات "تسميع" التاريخية القديمة
     * تبقى في القاعدة كما هي بلا أي ترحيل قسري (typeLabel() أدناه يعرضها
     * بقيمتها الخام إن وُجدت)، لكن لا يمكن إنشاء سطر جديد بهذا النوع بعد الآن.
     */
    public const TYPES = [
        'حفظ'    => 'حفظ جديد',
        'مراجعة' => 'مراجعة',
    ];

    /**
     * (S22) حلّت محلّ GRADES القديمة (ممتاز/جيد جدًا/جيد/مقبول) بطلب صريح من
     * يحيى — "حالة الحفظ" الجديدة حالتان مستقلّتان لكل طالب/يوم (واحدة للدرس
     * وواحدة للمراجعة، كلّ سجلّ يحمل حالته الخاصة لأنه نوع مستقلّ أصلًا)،
     * وليست تقييم جودة. "لم يسمع بعد" ليست قيمة مخزَّنة هنا إطلاقًا — هي غياب
     * أي سجلّ اليوم لهذا النوع، تُحسَب في طبقة العرض لا في هذا الحقل (سجلّ لا
     * يوجد بعد لا يمكن أن يحمل حالة). "غير حافظ" يحمل معه نفس بيانات الموضع
     * (from_ayah/to_ayah) تمامًا كأي سجلّ آخر — الحالة مستقلّة عن الموضع.
     *
     * هجرة البيانات (2026_09_20_000002) حوّلت كل سجلّ له قيمة grade قديمة
     * (أيًا كانت) إلى status = 'حافظ': وجود تقييم قديم أصلًا يعني أن الطالب
     * سمَّع شيئًا فعليًا، وهذا أقرب معنى لـ"حافظ" من دلالات الأربع القديمة.
     */
    public const STATUSES = [
        'حافظ'      => 'حافظ',
        'غير حافظ'  => 'غير حافظ',
    ];

    protected $fillable = [
        'student_id', 'surah_id', 'to_surah_id', 'from_ayah', 'to_ayah', 'type', 'status', 'notes', 'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /** سورة البداية ("من سورة") — الاسم بقي surah لتوافق كل شاشة قائمة. */
    public function surah()
    {
        return $this->belongsTo(Surah::class);
    }

    /** سورة النهاية الصريحة عند مراجعة عابرة لعدّة سور — قد تكون null. */
    public function toSurah()
    {
        return $this->belongsTo(Surah::class, 'to_surah_id');
    }

    /** true لو كان مدى السجلّ يمتدّ فعليًا لسورة نهاية مختلفة عن سورة البداية. */
    public function spansMultipleSurahs(): bool
    {
        return $this->to_surah_id !== null && $this->to_surah_id !== $this->surah_id;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** حالة الحفظ لهذا السجلّ (حافظ/غير حافظ) — نظير typeLabel()، راجع STATUSES أعلاه (S22). */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * سجلّا اليوم (حفظ ومراجعة) لكل طالب من مجموعة طلاب دفعة واحدة (S23) —
     * تغذية اللوحة الرئيسية (/dashboard) بعمودَي "الدرس"/"المراجعة"، بنفس
     * مبدأ Attendance::alertsFor()/todayStatusFor() بالضبط: استعلام واحد لكل
     * صفحة بدل استعلام لكل طالب (N+1). أحدث سطر فقط لكل نوع لو تكرّر التسجيل
     * اليوم لنفس النوع (تصحيح خطأ سابق مثلًا) — orderByDesc('id') يضمن ذلك.
     *
     * لا علاقة بـ latestMemorizationLog/latestReviewLog على Student (تلك آخر
     * سجلّ على الإطلاق أيًا كان تاريخه، تُستخدَم للاستكمال التلقائي للموضع
     * التالي؛ هذه محصورة بتاريخ اليوم تحديدًا لعرض حالة العمل اليومي).
     *
     * @return array<int, array{'حفظ': ?RecitationLog, 'مراجعة': ?RecitationLog}>
     */
    public static function todayLogsFor($studentIds, string $date): array
    {
        $rows = static::whereIn('student_id', $studentIds)
            ->whereDate('logged_at', $date)
            ->whereIn('type', ['حفظ', 'مراجعة'])
            ->with(['surah', 'toSurah'])
            ->orderByDesc('id')
            ->get()
            ->groupBy('student_id');

        $result = [];
        foreach ($rows as $studentId => $logs) {
            $result[$studentId] = [
                'حفظ'    => $logs->firstWhere('type', 'حفظ'),
                'مراجعة' => $logs->firstWhere('type', 'مراجعة'),
            ];
        }

        return $result;
    }
}
