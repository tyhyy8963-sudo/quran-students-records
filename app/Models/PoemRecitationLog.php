<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سطر واحد في السجلّ الزمني لحفظ/مراجعة متن (S15) — نفس فلسفة RecitationLog:
 * لا يُعدَّل بعد إنشائه، يُحذف فقط عبر "تراجع" فور إضافته. مستقلّ كليًا عن
 * recitation_logs (قرار صريح: لا مفتاح مشترك ولا استعلام مشترك).
 *
 * يعيد استخدام RecitationLog::TYPES وRecitationLog::GRADES لأن النوع والتقييم
 * نفس المفهوم حرفيًا لحفظ القرآن أو متن — لا حاجة لنسخة ثانية من نفس القيم.
 */
class PoemRecitationLog extends Model
{
    protected $fillable = [
        'student_id', 'poem_id', 'from_bayt', 'to_bayt', 'type', 'grade', 'notes', 'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function poem()
    {
        return $this->belongsTo(Poem::class);
    }

    public function typeLabel(): string
    {
        return RecitationLog::TYPES[$this->type] ?? $this->type;
    }
}
