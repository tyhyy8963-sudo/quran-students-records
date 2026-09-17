<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    /** القيمة المخزَّنة هي نفسها التسمية المعروضة — بلا ترجمة وسيطة (S9). */
    public const STATUSES = [
        'حاضر'   => 'حاضر',
        'غائب'   => 'غائب',
        'متأخر'  => 'متأخر',
        'مستأذن' => 'مستأذن',
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

        $alerts = [];
        foreach ($recent as $studentId => $rows) {
            $lastTwo = $rows->take(2);
            $alerts[$studentId] = $lastTwo->count() === 2
                && $lastTwo->every(fn (Attendance $a) => $a->status === 'غائب');
        }

        return $alerts;
    }
}
