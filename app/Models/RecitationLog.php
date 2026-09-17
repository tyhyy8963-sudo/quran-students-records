<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * سطر واحد في السجلّ الزمني لطالب (S7) — لا يُعدَّل بعد إنشائه، يُحذف
 * فقط عبر "تراجع" فور إضافته (نفس فلسفة حذف الطالب الناعم: خطأ يُصحَّح
 * فورًا لا يحتاج شاشة تعديل منفصلة).
 */
class RecitationLog extends Model
{
    public const TYPES = [
        'حفظ'    => 'حفظ جديد',
        'مراجعة' => 'مراجعة',
        'تسميع'  => 'تسميع',
    ];

    public const GRADES = [
        'ممتاز'   => 'ممتاز',
        'جيد جدا' => 'جيد جدًا',
        'جيد'     => 'جيد',
        'مقبول'   => 'مقبول',
    ];

    protected $fillable = [
        'student_id', 'surah_id', 'from_ayah', 'to_ayah', 'type', 'grade', 'notes', 'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function surah()
    {
        return $this->belongsTo(Surah::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
