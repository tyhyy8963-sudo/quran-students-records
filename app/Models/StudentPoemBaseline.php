<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * أرضية متن لطالب (S16) — "آخر بيت أتمّه الطالب قبل الانضمام"، تُستخدم كأرضية
 * حسابية في PoemProgress بدل تلفيق سجلّات تاريخية.
 *
 * هذه أرضية يدوية مقصودة، بخلاف حفظ القرآن (S15، مصحَّح): القرآن له ترتيب
 * حفظ واحد مشترك يمكن الاستدلال منه تلقائيًا (راجع
 * MemorizationProgress::applyCascade())، بينما المتون الخمسة تُتتبَّع
 * بالتوازي بلا سلّم ترتيب واحد بينها، فلا يوجد أساس تلقائي مماثل يُبنى عليه.
 */
class StudentPoemBaseline extends Model
{
    protected $fillable = [
        'student_id', 'poem_id', 'baseline_bayt',
    ];

    protected $casts = [
        'baseline_bayt' => 'integer',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function poem()
    {
        return $this->belongsTo(Poem::class);
    }
}
