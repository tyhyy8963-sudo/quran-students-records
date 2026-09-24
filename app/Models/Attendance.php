<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    /**
     * القيمة المخزَّنة هي نفسها التسمية المعروضة — بلا ترجمة وسيطة (S9).
     *
     * محدَّثة في المرحلة الجديدة (S22، بطلب صريح من يحيى): "متأخر" أُلغيت
     * كليًا، و"غائب" انقسمت إلى معذور/غير معذور. هجرة البيانات المرافقة
     * (2026_09_20_000001) حوّلت كل سجلّ "متأخر" قديم إلى "حاضر"، وكل سجلّ
     * "غائب" قديم (بلا تمييز عذر) إلى "غائب بدون عذر" — كلا القرارين صدر عن
     * يحيى صراحةً، لا افتراضًا.
     */
    public const STATUSES = [
        'حاضر'            => 'حاضر',
        'مستأذن'          => 'مستأذن',
        'غائب بعذر'       => 'غائب بعذر',
        'غائب بدون عذر'   => 'غائب بدون عذر',
    ];

    protected $fillable = [
        'student_id',
        'date',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    /**
     * مؤشّر انقطاع (S9): آخر سطرَي حضور مسجَّلين للطالب كلاهما «غائب».
     * استعلام واحد لكل الطلاب المطلوبين بدل استعلام لكل طالب (N+1)، محدود
     * بآخر 30 يومًا حتى لا يمرّ على كامل تاريخ الحضور القديم.
     *
     * تبسيط معلَن: يتجاهل الأيام التي لم يُسجَّل فيها أي حضور إطلاقًا (لا
     * تُحتسَب غيابًا ولا حضورًا) — ليس "غياب فعلي منذ كذا يوم" بل "آخر سطرين
     * مسجَّلين فقط". يُستخدم في لوحة الطلاب (S9) ولوحة التقارير (S10) معًا،
     * حتى لا يتكرّر نفس الحساب في مكانين قد يختلفان لاحقًا.
     */
    public static function alertsFor($studentIds): array
    {
        $recent = static::whereIn('student_id', $studentIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('date')
            ->get()
            ->groupBy('student_id');

        // "غائب" (S22): القيمة القديمة قبل انقسام الحالة إلى معذور/غير معذور
        // ما زالت مقبولة هنا دفاعيًا رغم أن هجرة البيانات حوّلت كل سطر قديم
        // بها إلى "غائب بدون عذر" — أي غياب بأي نوع يُعتبر انقطاعًا لهذا
        // المؤشر، فلا داعي لتفريقه هنا.
        $absenceStatuses = ['غائب', 'غائب بعذر', 'غائب بدون عذر'];

        $alerts = [];
        foreach ($recent as $studentId => $rows) {
            $lastTwo = $rows->take(2);
            $alerts[$studentId] = $lastTwo->count() === 2
                && $lastTwo->every(fn (Attendance $a) => in_array($a->status, $absenceStatuses, true));
        }

        return $alerts;
    }

    /**
     * حالة حضور اليوم لكل طالب (S18) — لعرض شارة "حضور اليوم" في اللوحة
     * الرئيسية وفلترة الطلاب بها، بنفس مبدأ alertsFor(): استعلام واحد
     * لكل طلاب الصفحة بدل استعلام لكل طالب. طالب بلا سطر لليوم غائب من
     * المصفوفة الناتجة كليًا (لا قيمة null صريحة) — الفارق بين "لم يُسجَّل
     * بعد" و"سُجِّل غائبًا" مهمّ هنا، فلا يصحّ دمجهما في نفس القيمة.
     *
     * @return array<int, string> [student_id => حالة اليوم]
     */
    public static function todayStatusFor($studentIds): array
    {
        return static::whereIn('student_id', $studentIds)
            ->whereDate('date', now()->toDateString())
            ->pluck('status', 'student_id')
            ->all();
    }
}
