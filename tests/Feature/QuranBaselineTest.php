<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * الانسياب التلقائي لنسبة الحفظ، بوزن الأرباع الـ240 (S16، التصحيح الثاني).
 *
 * جُرِّب أولًا عمود "أرضية" (quran_baseline_surah_id) يختاره المعلّم يدويًا —
 * قرار رفضه صاحب المنظومة صراحةً. ثم صُحِّحت النسبة نفسها لاحقًا لتوزَن
 * بالأرباع الـ240 (حجم المصحف الفعلي) لا بعدد السور المتساوي، إذ الأخير كان
 * يُعطي طلاب أواخر القرآن نسبة مضخَّمة (79% بدل 40% فعليًا لسور طويلة متبقّية
 * في أول المصحف). الانسياب التلقائي نفسه لم يتغيّر بهذا التصحيح الثاني — ما
 * تغيّر هو كيف تُترجَم كل خطوة مكتملة إلى نسبة. راجع تعليق الصنف في
 * MemorizationProgress للتفصيل الكامل.
 *
 * السور المُختارة هنا (الملك 67، الأعلى 87، الشرح 94) ليست عشوائية: كل واحدة
 * تبدأ عند حدّ ربع بالضبط (جدول حدود الأرباع الحقيقي)، فتُصبح كل نسبة متوقَّعة
 * هنا مشتقّة من عدد صحيح من الأرباع بلا حاجة لحساب كسر يدوي عرضة للخطأ — إلا
 * حيث الاختبار يقصد فحص الكسر نفسه (السورة الجزئية)، وهناك يُشتقّ الكسر من
 * طول الربع الحقيقي المقروء من القاعدة، لا رقم مكتوب يدويًا. كل اختبار يبدأ
 * بتحقّق دفاعي (assertSame) على حدّ الربع المُستخدَم؛ لو انزاح جدول الحدود
 * يومًا ما لأي سبب، الاختبار يفشل بوضوح بدل حساب نسبة متوقَّعة خاطئة بصمت —
 * وهذا هو المطلوب بالتحديد حول عدّاد لا يُحتمَل فيه أي خطأ.
 */
class QuranBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private MemorizationProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->progress = app(MemorizationProgress::class);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    private function quarter(int $number): Quarter
    {
        return Quarter::where('quarter_number', $number)->firstOrFail();
    }

    private function log(int $surahNumber, int $to, ?int $from = null, string $type = 'حفظ'): void
    {
        $this->student->recitationLogs()->create([
            'surah_id'  => $this->surah($surahNumber)->id,
            'from_ayah' => $from,
            'to_ayah'   => $to,
            'type'      => $type,
            'logged_at' => now()->toDateString(),
        ]);

        $this->progress->forget($this->student);
    }

    /** @test */
    public function logging_a_distant_surah_auto_credits_every_earlier_step(): void
    {
        // الملك (67) تبدأ عند حدّ الربع 225 بالضبط — تسجيلها كاملة يعني: من
        // بداية الربع 225 حتى نهاية القرآن مغطّى بالكامل (هي نفسها + الانسياب
        // على كل ما بعدها)، أي الأرباع 225 إلى 240 كاملة بلا أي كسر.
        $mulk = $this->surah(67);
        $quarter225 = $this->quarter(225);
        $this->assertSame(1, $quarter225->start_ayah);
        $this->assertSame($mulk->id, $quarter225->start_surah_id);

        $this->log(67, $mulk->ayah_count);

        $expected = round((Quarter::COUNT - $quarter225->quarter_number + 1) / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertCount($mulk->memorization_order, $this->progress->completedSurahs($this->student));
        $this->assertSame('الملك', $this->progress->furthestSurah($this->student)->name);
    }

    /** @test */
    public function the_furthest_surahs_own_partial_coverage_is_not_rounded_up(): void
    {
        // الأعلى (87) تبدأ عند حدّ الربع 237 بالضبط، لكنها لا تملأ الربع
        // كلّه وحدها (الغاشية والفجر يشاركانه إلى أن يبدأ الربع 238). نصفها
        // فقط مُسجَّل هنا، وبقيّة الربع 237 (ما بعد نهاية الأعلى) ينساب
        // تلقائيًا رغم مشاركتها نفس ربع السورة الجزئية — لأن الانسياب مبنيّ
        // على محور الآيات المطلق لا على وحدة "الربع".
        $ala = $this->surah(87);
        $quarter237 = $this->quarter(237);
        $quarter238 = $this->quarter(238);
        $this->assertSame(1, $quarter237->start_ayah);
        $this->assertSame($ala->id, $quarter237->start_surah_id);

        $half = intdiv($ala->ayah_count, 2);
        $this->log(87, $half);

        $quarter237Length = $quarter238->start_global_ayah - $quarter237->start_global_ayah;
        $coveredInQuarter237 = $half + ($quarter237Length - $ala->ayah_count);

        $steps = ($coveredInQuarter237 / $quarter237Length) + (Quarter::COUNT - $quarter237->quarter_number);
        $expected = round($steps / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertCount($ala->memorization_order - 1, $this->progress->completedSurahs($this->student));
    }

    /** @test */
    public function moving_to_a_further_surah_re_cascades_from_the_new_position(): void
    {
        // الشرح (94) عند حدّ الربع 239، والملك (67) عند حدّ الربع 225 — الملك
        // أبعد في ترتيب الحفظ (رتبتها أكبر) رغم أنها سُجِّلت ثانيًا هنا.
        $sharh = $this->surah(94);
        $mulk = $this->surah(67);
        $quarter225 = $this->quarter(225);
        $quarter239 = $this->quarter(239);
        $this->assertSame(1, $quarter239->start_ayah);
        $this->assertSame($sharh->id, $quarter239->start_surah_id);
        $this->assertSame(1, $quarter225->start_ayah);
        $this->assertSame($mulk->id, $quarter225->start_surah_id);

        $this->log(94, $sharh->ayah_count);
        $this->log(67, $mulk->ayah_count);

        // النتيجة النهائية مطابقة تمامًا لتسجيل الملك وحدها (الاختبار الأول):
        // مدى الشرح بالكامل يقع أصلًا داخل ذيل انسياب الملك، فلا يضيف شيئًا.
        $expected = round((Quarter::COUNT - $quarter225->quarter_number + 1) / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $this->student->progressPercentage());
        $this->assertSame('الملك', $this->progress->furthestSurah($this->student)->name);
    }

    /** @test */
    public function logging_an_earlier_surah_after_the_furthest_does_not_change_the_percentage(): void
    {
        $mulk = $this->surah(67);
        $this->log(67, $mulk->ayah_count);

        $before = $this->student->progressPercentage();

        // الشرح أسبق من الملك في ترتيب الحفظ — مُحتسَبة كاملة بالانسياب أصلًا،
        // فتسجيلها فعليًا لاحقًا لا يرفع الرقم (اتحاد مديات لا جمع).
        $sharh = $this->surah(94);
        $this->log(94, $sharh->ayah_count);

        $this->assertSame($before, $this->student->progressPercentage());
    }

    /** @test */
    public function review_type_logs_never_trigger_the_cascade(): void
    {
        $yusuf = $this->surah(12);

        // "مراجعة" لسورة متأخرة يجب ألّا تُفعِّل الانسياب أصلًا — الحساب مبنيّ
        // فقط على سجلّات نوع "حفظ" حصرًا.
        $this->log(12, $yusuf->ayah_count, null, 'مراجعة');

        $this->assertSame(0.0, $this->student->progressPercentage());
        $this->assertCount(0, $this->progress->completedSurahs($this->student));
        $this->assertNull($this->progress->furthestSurah($this->student));
    }

    /** @test */
    public function warm_for_applies_the_same_cascade_as_a_single_lookup(): void
    {
        $mulk = $this->surah(67);
        $quarter225 = $this->quarter(225);
        $this->log(67, $mulk->ayah_count);

        $other = Student::create(['student_name' => 'محمد', 'teacher_id' => $this->teacher->id]);

        $fresh = app(MemorizationProgress::class);
        $fresh->warmFor(collect([$this->student, $other]));

        $expected = round((Quarter::COUNT - $quarter225->quarter_number + 1) / Quarter::COUNT * 100, 1);

        $this->assertSame($expected, $fresh->percentage($this->student));
        $this->assertSame(0.0, $fresh->percentage($other));
    }

    /** @test */
    public function students_with_no_logs_have_zero_percent(): void
    {
        $this->assertSame(0.0, $this->student->progressPercentage());
        $this->assertCount(0, $this->progress->completedSurahs($this->student));
        $this->assertNull($this->progress->furthestSurah($this->student));
    }
}
