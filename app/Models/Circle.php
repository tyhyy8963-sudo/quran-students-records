<?php

namespace App\Models;

use App\Models\Scopes\TeacherScope;
use Illuminate\Database\Eloquent\Model;

/**
 * الحلقة (S6) — مجموعة طلاب تخصّ معلّمًا واحدًا.
 *
 * نفس TeacherScope المستخدم في Student: يعزل حلقات كل معلّم عن الآخر
 * تلقائيًا بمجرد تسجيل الدخول، بلا شرط where يتكرّر في كل متحكّم.
 */
class Circle extends Model
{
    protected $fillable = ['teacher_id', 'name'];

    protected static function booted()
    {
        static::addGlobalScope(new TeacherScope);

        static::creating(function (Circle $circle) {
            $circle->teacher_id ??= auth()->id();
        });

        /**
         * قيد `onDelete('set null')` في الهجرة كافٍ على MySQL (الإنتاج)، لكنه
         * لا يُنشأ فعليًا على SQLite حين تُضاف علاقة مفتاح أجنبي عبر
         * `Schema::table` (تعديل) بدل `Schema::create` — قيد معروف في محرّك
         * SQLite نفسه، لا في Laravel. بيئة الاختبار الآلي (phpunit) تستخدم
         * SQLite، فهذا الحدث يضمن نفس السلوك على كل قاعدة بيانات دون
         * الاعتماد على دعم المحرّك لهذا النوع من القيود.
         */
        static::deleting(function (Circle $circle) {
            $circle->students()->update(['circle_id' => null]);
        });
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'circle_id', 'id');
    }
}
