<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * أرضية متن لطالب (S16) — "آخر بيت أتمّه الطالب قبل الانضمام"، تُستخدم كأرضية
 * حسابية في PoemProgress بدل تلفيق سجلّات تاريخية. نفس فكرة
 * Student::quran_baseline_surah_id لكل متن على حدة.
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
