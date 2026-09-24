<?php

namespace App\Models;

use App\Models\Scopes\TeacherScope;
use App\Support\MemorizationProgress;
use App\Support\PoemProgress;
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

    public function poemRecitationLogs()
    {
        return $this->hasMany(PoemRecitationLog::class, 'student_id', 'student_id')
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
     * آخر موضع مراجعة جديد مسجَّل (S15) — نظير latestMemorizationLog تمامًا،
     * لكن لنوع "مراجعة"، حتى تعرض بطاقة الطالب آخر موضعين لا موضعًا واحدًا:
     * أين وصل حفظًا، وأين وصل مراجعةً (وقد يختلفان تمامًا).
     */
    public function latestReviewLog()
    {
        return $this->hasOne(RecitationLog::class, 'student_id', 'student_id')
            ->ofMany(['logged_at' => 'max', 'id' => 'max'], function ($query) {
                $query->where('type', 'مراجعة');
            });
    }

    /**
     * المتون التي يتتبّعها الطالب فعليًا (S15) — التتبّع متوازٍ لا تسلسلي (قرار
     * صريح): قد يعمل الطالب على أكثر من متن من الخمسة معًا، فلا معنى لـ"المتن
     * النشط الحالي" الواحد. متن "يُتتبَّع" إن كان له سجلّ حفظ/مراجعة واحد على
     * الأقل.
     *
     * (S24 — بطلب صريح من يحيى): كانت "أرضية متن" يدوية (student_poem_baselines)
     * مصدرًا ثانيًا هنا أيضًا. أُلغيت نهائيًا لنفس السبب الذي أُلغيت به أرضية
     * حفظ القرآن اليدوية سابقًا (راجع MemorizationProgress) — لا إدخال يدوي
     * لـ"ما قبل الانضمام"، الاعتماد كليًا على السجلّات الفعلية المسجَّلة.
     *
     * @return \Illuminate\Support\Collection<int, Poem>
     */
    public function trackedPoems()
    {
        $ids = $this->poemRecitationLogs()->pluck('poem_id')->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Poem::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** آخر سطر حفظ أو مراجعة لمتن معيّن — نظير latestMemorizationLog/latestReviewLog لكن للمتون (S15). */
    public function latestPoemLog(int $poemId, string $type): ?PoemRecitationLog
    {
        return $this->poemRecitationLogs()
            ->where('poem_id', $poemId)
            ->where('type', $type)
            ->first();
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

    /** نسبة حفظ متن معيّن للطالب (S15) — واجهة مريحة فوق PoemProgress، بنفس فلسفة progressPercentage(). */
    public function poemProgressPercentage(Poem $poem): float
    {
        return app(PoemProgress::class)->percentage($this, $poem);
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

    /**
     * أتمّ حفظ القرآن كاملًا؟ وسم منفصل لا حالة بديلة (S16) — "متخرّج" كانت
     * حالة في status تتعارض مع inactive/transferred (طالب متخرّج قد يبقى
     * منتقلًا أو منقطعًا)، بينما هذا وسم يُحسَب من نسبة الحفظ الفعلية دائمًا،
     * فلا يتيه من التحديث اليدوي لحقل status.
     */
    public function hasCompletedQuran(): bool
    {
        return $this->progressPercentage() >= 100.0;
    }

    /** أتمّ حفظ متن معيّن بالكامل؟ نظير hasCompletedQuran لكن لمتن واحد (S15). */
    public function hasCompletedPoem(Poem $poem): bool
    {
        return $this->poemProgressPercentage($poem) >= 100.0;
    }

    /**
     * عدد المتون المتتبَّعة لكل طالب من مجموعة طلاب دفعة واحدة (S18) — لعرض
     * شارة مختصرة "٢ متن متتبَّع" في اللوحة الرئيسية بلا استعلام trackedPoems()
     * لكل طالب على حدة (N+1 في صفحة فيها خمسون طالبًا). لا يحسب نِسَبًا — تلك
     * موجودة بدقّة في صفحة الطالب نفسها (S15/S16)، وحسابها لخمسين طالبًا معًا
     * هنا مكلف بلا داعٍ لمجرّد شارة عدد.
     *
     * (S24) كان مصدر ثانٍ هنا أيضًا student_poem_baselines (الأرضية اليدوية
     * المُلغاة) — راجع تعليق trackedPoems() أعلاه لسبب الإلغاء الكامل.
     *
     * @return array<int, int> [student_id => عدد المتون المتتبَّعة]
     */
    public static function trackedPoemCountsFor($studentIds): array
    {
        $ids = collect($studentIds)->map(fn ($id) => (int) $id)->all();

        if (empty($ids)) {
            return [];
        }

        $fromLogs = PoemRecitationLog::query()
            ->whereIn('student_id', $ids)
            ->select('student_id', 'poem_id')
            ->distinct()
            ->get();

        $poemIdsByStudent = [];
        foreach ($fromLogs as $row) {
            $poemIdsByStudent[$row->student_id][$row->poem_id] = true;
        }

        return array_map('count', $poemIdsByStudent);
    }
}
