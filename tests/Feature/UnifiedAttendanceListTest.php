<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * اللوحة الرئيسية الموحّدة (S23) — الدرس + المراجعة + الحضور لكل الطلاب
 * (أو حلقة واحدة) في صفحة واحدة، مع تبويبات حلقات فعلية وفلتر أولوية حسب
 * الحالة (بند 8/9 من تقرير التطوير).
 *
 * تصحيح مهمّ (بعد فهم خاطئ أوّلي): هذه الميزة بُنيت أول مرة على /attendance
 * ظنًّا بأنها "الصفحة الرئيسية" التي قصدها يحيى، فصحّح صراحةً أن "الصفحة
 * الرئيسية" كانت تعني طوال الوقت صفحة "الطلاب" (/dashboard)، وأن /attendance
 * يجب أن تبقى للعرض فقط. هذا الملف يختبر القائمة الموحّدة على /dashboard
 * كما هي الآن — راجع AttendanceTest لاختبارات /attendance كشاشة عرض.
 */
class UnifiedAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ ماجد', 'username' => 'majed', 'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function a_student_with_no_log_today_shows_not_yet_recorded_for_lesson_and_review(): void
    {
        Student::create(['student_name' => 'طالب بلا تسجيل اليوم', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-state="لم يسمع بعد"', false);
    }

    /** @test */
    public function a_recorded_lesson_today_shows_its_range_with_the_surah_name(): void
    {
        $surah = Surah::where('number', 114)->firstOrFail();
        $student = Student::create(['student_name' => 'طالب له درس اليوم', 'teacher_id' => $this->teacher->id]);
        $student->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'status' => 'حافظ',
            'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-state="حافظ"', false)
            // "حتى آية 3" لا يظهر إلا من مكوّن عرض النطاق نفسه (لا من قائمة
            // السور المنسدلة، التي تسرد كل الأسماء بصرف النظر عن أي سجلّ).
            ->assertSee('حتى آية 3');
    }

    /** @test */
    public function a_lesson_marked_not_memorized_today_shows_the_red_label_not_the_range(): void
    {
        $surah = Surah::where('number', 114)->firstOrFail();
        $student = Student::create(['student_name' => 'طالب لم يحفظ اليوم', 'teacher_id' => $this->teacher->id]);
        $student->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'status' => 'غير حافظ',
            'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-state="غير حافظ"', false)
            ->assertSee('لم يحفظ');
    }

    /** @test */
    public function a_recorded_review_today_shows_its_raw_range_without_a_computed_amount(): void
    {
        // (تصحيح بعد القرار #54، طلب صريح من يحيى): كان هذا الاختبار يتأكَّد
        // من ظهور تسمية كمّية محسوبة تلقائيًا مثل "(ربع جزء)"/"(جزء كامل)"
        // بجانب مدى المراجعة — يحيى طلب حذف هذا الحساب نهائيًا وعرض المدى
        // الخام فقط ("خلي الخيار الموجود حاليا... وخليه بدون حساب للأجزاء
        // أو الأحزاب"). الاختبار الآن يتأكَّد من العكس تمامًا: المدى الخام
        // ظاهر، وصنف `.review-amount` (تسمية الكمّية القديمة) غير موجود
        // إطلاقًا في استجابة الصفحة.
        $baqarah = Surah::where('number', 2)->firstOrFail();
        $student = Student::create(['student_name' => 'طالب له مراجعة اليوم', 'teacher_id' => $this->teacher->id]);
        $student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'from_ayah' => 1, 'to_ayah' => $baqarah->ayah_count,
            'type' => 'مراجعة', 'status' => 'حافظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('البقرة')
            ->assertDontSee('class="review-amount"', false);
    }

    /** @test */
    public function circle_tabs_show_the_teachers_actual_circle_names_not_prayer_times(): void
    {
        // تصحيح لاحق من يحيى: "الفجر/الظهر/المغرب" في التقرير الأصلي كانت
        // مجرّد أمثلة توضيحية لأسماء حلقات حقيقية موجودة أصلًا، لا فترات
        // صلاة ولا حقل جديد يُقترَح إضافته.
        Circle::create(['name' => 'الفرقان', 'teacher_id' => $this->teacher->id]);
        Circle::create(['name' => 'النور', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('الفرقان')
            ->assertSee('النور')
            ->assertSee('الكل')
            ->assertDontSee('الظهر')
            ->assertDontSee('المغرب');
    }

    /** @test */
    public function memorization_priority_filter_brings_matching_students_to_the_top_without_hiding_others(): void
    {
        $surah = Surah::where('number', 114)->firstOrFail();

        $normal = Student::create(['student_name' => 'طالب أ عادي', 'teacher_id' => $this->teacher->id]);
        $normal->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'status' => 'حافظ',
            'logged_at' => now()->toDateString(),
        ]);

        $notMemorized = Student::create(['student_name' => 'طالب ب لم يحفظ', 'teacher_id' => $this->teacher->id]);
        $notMemorized->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'status' => 'غير حافظ',
            'logged_at' => now()->toDateString(),
        ]);

        // بلا فلتر: الترتيب أبجدي (طالب أ قبل طالب ب) — الأولوية تعكسه.
        $this->actingAs($this->teacher)
            ->get('/dashboard?'.http_build_query(['memorization_priority' => 'غير حافظ']))
            ->assertOk()
            ->assertSeeInOrder(['طالب ب لم يحفظ', 'طالب أ عادي'])
            // لا استبعاد (S23 بند 9): كلا الطالبين يظهران معًا دومًا، هذا
            // ترتيب لا فلتر إخفاء.
            ->assertSee('طالب أ عادي')
            ->assertSee('طالب ب لم يحفظ');
    }

    /** @test */
    public function attendance_priority_filter_brings_matching_students_to_the_top(): void
    {
        $present = Student::create(['student_name' => 'طالب أ حاضر', 'teacher_id' => $this->teacher->id]);
        $present->attendances()->create(['date' => now()->toDateString(), 'status' => 'حاضر']);

        $absent = Student::create(['student_name' => 'طالب ب غائب', 'teacher_id' => $this->teacher->id]);
        $absent->attendances()->create(['date' => now()->toDateString(), 'status' => 'غائب بدون عذر']);

        $this->actingAs($this->teacher)
            ->get('/dashboard?'.http_build_query(['attendance_priority' => 'غائب بدون عذر']))
            ->assertOk()
            ->assertSeeInOrder(['طالب ب غائب', 'طالب أ حاضر']);
    }

    /**
     * (تصحيح ثامن، بطلب صريح من يحيى "خليه مطابق 100%" لمخطّطه الجديد):
     * الصفّ المطوي عند الغياب (شارة عريضة واحدة تحلّ محلّ الدرس/المراجعة،
     * وتلوين الصفّ كاملًا أصفر عبر class="row-absent") أُلغي نهائيًا. الآن
     * "غائب" يظهر كبطاقة فردية صغيرة بنفس شكل/موضع "حاضر" ضمن عمود حضور
     * اليوم فقط، وبطاقتا الدرس والمراجعة تبقيان ظاهرتَين دومًا بصرف النظر
     * عن حالة الحضور — يستبدل الاختبار القديم
     * an_absent_student_row_is_flagged_for_the_whole_row_highlight الذي كان
     * يتوقّع السلوك المُلغى.
     */
    /** @test */
    public function an_absent_student_still_shows_individual_lesson_and_review_chips_not_a_folded_row(): void
    {
        $student = Student::create(['student_name' => 'طالب غائب اليوم', 'teacher_id' => $this->teacher->id]);
        $student->attendances()->create(['date' => now()->toDateString(), 'status' => 'غائب بعذر']);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('row-absent', false)
            ->assertDontSee('chip-attendance--absent', false)
            ->assertSee('غائب بعذر')
            // الدرس/المراجعة لم يُسجَّلا اليوم لهذا الطالب، فتبقى بطاقتاهما
            // ظاهرتَين بحالة "لم يسمع بعد" العادية — لا طيّ ولا إخفاء.
            ->assertSee('data-state="لم يسمع بعد"', false);
    }
}
