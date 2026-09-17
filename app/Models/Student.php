<?php

namespace App\Models;

use App\Models\Scopes\TeacherScope;
use App\Support\MemorizationProgress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    /** الحالات الصالحة لحقل status (B-05). */
    public const STATUSES = [
        'active'      => 'نشط',
        'inactive'    => 'منقطع',
        'transferred' => 'منتقل',
        'graduated'   => 'متخرّج',
    ];

    protected $primaryKey = 'student_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'student_name',
        'teacher_id',
        'circle_id',
        'status',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * القيمة الافتراضية في العمود نفسه (عبر الهجرة) لا تنعكس على النموذج في
     * الذاكرة مباشرة بعد Student::create() إلا بعد استرجاعه من القاعدة —
     * فيبقى $student->status فارغًا في اللحظة نفسها التي يُبنى فيها StudentResource
     * من نتيجة store()، فيفشل statusLabel(). هذا هو نفس الافتراضي، مضبوطًا هنا
     * أيضًا ليكون متاحًا فورًا في الذاكرة لا بعد استعلام إضافي.
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted()
    {
        // عزل بيانات المعلّمين كجزء من تعريف الموديل، لا شرط يتكرّر
        // كتابته يدويًا في كل متحكّم (B-04).
        static::addGlobalScope(new TeacherScope);

        static::creating(function (Student $student) {
            $student->teacher_id ??= auth()->id();
        });
    }

    /**
     * إجمالي آيات القرآن (رواية حفص).
     *
     * كان مقام نسبة التقدّم حتى S13 (نسبة بترتيب المصحف). لم يعد كذلك منذ S14:
     * السلّم صار عدد السور في ترتيب الحفظ المعكوس (Surah::COUNTABLE_COUNT).
     * يبقى الثابت لأنه حقيقة عن المصحف تُستعمل في العرض، لا رقمًا محسوبًا.
     */
    public const TOTAL_AYAT = 6236;

    public function circle()
    {
        return $this->belongsTo(Circle::class, 'circle_id', 'id');
    }

    public function recitationLogs()
    {
        return $this->hasMany(RecitationLog::class, 'student_id', 'student_id')
            ->orderByDesc('logged_at')
            ->orderByDesc('id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'student_id', 'student_id');
    }

    /**
     * آخر موضع حفظ جديد مسجَّل — أساس نسبة التقدّم والعرض السريع في اللوحة.
     * "مراجعة"/"تسميع" لا تُغيّر الموضع، فهي أحداث عن مقطع سابق لا تقدّم جديد.
     *
     * علاقة hasOne...ofMany بدل استعلام يدوي: تسمح بالتحميل المسبق
     * (`Student::with('latestMemorizationLog')`) فلا يتكرّر استعلام لكل
     * طالب عند عرض قائمة كاملة (N+1) كما لو كانت دالة عادية.
     */
    public function latestMemorizationLog()
    {
        return $this->hasOne(RecitationLog::class, 'student_id', 'student_id')
            ->ofMany(['logged_at' => 'max', 'id' => 'max'], function ($query) {
                $query->where('type', 'حفظ');
            });
    }

    /**
     * نسبة الحفظ بترتيب الحفظ المعكوس: الناس ≈ 1% … البقرة = 100% (S14).
     *
     * الحساب كلّه في MemorizationProgress — صنف واحد تستدعيه اللوحة وصفحة
     * الطالب والتقارير والتصدير جميعًا. النموذج هنا واجهة مريحة لا نسخة ثانية
     * من المنطق: نسخة ثانية تعني رقمين مختلفين لنفس الطالب في شاشتين، وهو
     * بالضبط ما وقع في تقرير الفترة قبل توحيد periodRows() في S10.
     */
    public function progressPercentage(): float
    {
        return app(MemorizationProgress::class)->percentage($this);
    }

    /** أبعد سورة بلغها الطالب في تسلسل الحفظ (مكتملة أو قيد الحفظ). */
    public function furthestSurah(): ?Surah
    {
        return app(MemorizationProgress::class)->furthestSurah($this);
    }

    /** عدد السور التي أتمّها كاملة (الفاتحة غير محسوبة). */
    public function completedSurahsCount(): int
    {
        return app(MemorizationProgress::class)->completedSurahs($this)->count();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status ?? self::STATUSES['active'];
    }
}
