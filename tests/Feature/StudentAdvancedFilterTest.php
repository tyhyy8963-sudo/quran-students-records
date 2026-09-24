<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * فلترة متقدّمة للوحة الطلاب (S18): اختيار متعدّد للحلقة/الحالة، نطاق تاريخ
 * نشاط، نطاق نسبة تقدّم، وحضور اليوم — فوق فلترة S11 الفردية (لا تحلّ محلّها،
 * راجع StudentSearchSortTest للفلترة الفردية القديمة التي تبقى تعمل كما هي).
 */
class StudentAdvancedFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ سالم', 'username' => 'salem', 'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function selecting_two_circles_together_returns_the_union_of_both(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        $circleB = Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);
        $circleC = Circle::create(['name' => 'حلقة ج', 'teacher_id' => $this->teacher->id]);

        Student::create(['student_name' => 'طالب أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'طالب ب', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleB->id]);
        Student::create(['student_name' => 'طالب ج', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleC->id]);

        $this->actingAs($this->teacher)
            ->get("/dashboard?circle_id[]={$circleA->id}&circle_id[]={$circleB->id}")
            ->assertOk()
            ->assertSee('طالب أ')
            ->assertSee('طالب ب')
            ->assertDontSee('طالب ج');
    }

    /** @test */
    public function selecting_none_alongside_a_real_circle_includes_circleless_students_too(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);

        Student::create(['student_name' => 'طالب في أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'طالب بلا حلقة', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get("/dashboard?circle_id[]={$circleA->id}&circle_id[]=none")
            ->assertOk()
            ->assertSee('طالب في أ')
            ->assertSee('طالب بلا حلقة');
    }

    /** @test */
    public function selecting_two_statuses_together_returns_the_union_of_both(): void
    {
        Student::create(['student_name' => 'طالب نشط', 'teacher_id' => $this->teacher->id, 'status' => 'active']);
        Student::create(['student_name' => 'طالب منقطع', 'teacher_id' => $this->teacher->id, 'status' => 'inactive']);
        Student::create(['student_name' => 'طالب منتقل', 'teacher_id' => $this->teacher->id, 'status' => 'transferred']);

        $this->actingAs($this->teacher)
            ->get('/dashboard?status[]=active&status[]=inactive')
            ->assertOk()
            ->assertSee('طالب نشط')
            ->assertSee('طالب منقطع')
            ->assertDontSee('طالب منتقل');
    }

    /** @test */
    public function a_single_circle_id_still_works_the_old_way(): void
    {
        // توافق خلفي (S18): الصيغة الفردية القديمة (?circle_id=5) يجب أن
        // تستمرّ بالعمل كما هي، لا أن تنكسر بمجرّد إضافة الاختيار المتعدّد.
        $circle = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'طالب داخل الحلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circle->id]);
        Student::create(['student_name' => 'طالب آخر', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get("/dashboard?circle_id={$circle->id}")
            ->assertOk()
            ->assertSee('طالب داخل الحلقة')
            ->assertDontSee('طالب آخر');
    }

    /** @test */
    public function activity_date_range_only_shows_students_with_a_log_in_that_range(): void
    {
        $surah = Surah::where('number', 114)->firstOrFail();

        $active = Student::create(['student_name' => 'طالب نشط بالفترة', 'teacher_id' => $this->teacher->id]);
        $active->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'logged_at' => '2026-03-15',
        ]);

        $outside = Student::create(['student_name' => 'طالب خارج الفترة', 'teacher_id' => $this->teacher->id]);
        $outside->recitationLogs()->create([
            'surah_id' => $surah->id, 'to_ayah' => 3, 'type' => 'حفظ', 'logged_at' => '2026-01-01',
        ]);

        Student::create(['student_name' => 'طالب بلا سجلّات', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?activity_from=2026-03-01&activity_to=2026-03-31')
            ->assertOk()
            ->assertSee('طالب نشط بالفترة')
            ->assertDontSee('طالب خارج الفترة')
            ->assertDontSee('طالب بلا سجلّات');
    }

    /** @test */
    public function progress_range_only_shows_students_within_that_percentage_window(): void
    {
        // البقرة (السورة الأولى بترتيب المصحف) هي أبعد خطوة بترتيب الحفظ
        // المعكوس — حفظها كاملة يعني 100% دون الحاجة لحفظ كل ما قبلها فعليًا
        // بفضل الانسياب التلقائي (S15)، فطالب واحد يكفي لتمثيل الطرف الأعلى.
        $baqarah = Surah::where('number', 2)->firstOrFail();
        $naas = Surah::where('number', 114)->firstOrFail();

        $high = Student::create(['student_name' => 'طالب متقدّم', 'teacher_id' => $this->teacher->id]);
        $high->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'to_ayah' => $baqarah->ayah_count, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $low = Student::create(['student_name' => 'طالب مبتدئ', 'teacher_id' => $this->teacher->id]);
        $low->recitationLogs()->create([
            'surah_id' => $naas->id, 'to_ayah' => 3, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?progress_min=50')
            ->assertOk()
            ->assertSee('طالب متقدّم')
            ->assertDontSee('طالب مبتدئ');
    }

    /** @test */
    public function attendance_today_filter_shows_only_students_with_that_recorded_status(): void
    {
        $present = Student::create(['student_name' => 'طالب حاضر اليوم', 'teacher_id' => $this->teacher->id]);
        $present->attendances()->create(['date' => now()->toDateString(), 'status' => 'حاضر']);

        $absent = Student::create(['student_name' => 'طالب غائب اليوم', 'teacher_id' => $this->teacher->id]);
        $absent->attendances()->create(['date' => now()->toDateString(), 'status' => 'غائب بدون عذر']);

        Student::create(['student_name' => 'طالب بلا تسجيل اليوم', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?attendance_today='.urlencode('حاضر'))
            ->assertOk()
            ->assertSee('طالب حاضر اليوم')
            ->assertDontSee('طالب غائب اليوم')
            ->assertDontSee('طالب بلا تسجيل اليوم');
    }

    /** @test */
    public function attendance_today_not_recorded_shows_only_students_with_no_row_for_today(): void
    {
        $present = Student::create(['student_name' => 'طالب مسجَّل اليوم', 'teacher_id' => $this->teacher->id]);
        $present->attendances()->create(['date' => now()->toDateString(), 'status' => 'حاضر']);

        Student::create(['student_name' => 'طالب غير مسجَّل اليوم', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?attendance_today=not_recorded')
            ->assertOk()
            ->assertSee('طالب غير مسجَّل اليوم')
            ->assertDontSee('طالب مسجَّل اليوم');
    }

    /**
     * (تصحيح S23، ثم تصحيح بصري ثالث): عمود "آخر موضع" استُبدل بعمود
     * "المراجعة" الذي يعرض حالة *اليوم* تحديدًا (بند 3 من تقرير التطوير) —
     * سجلّ اليوم في هذا الاختبار يجعل الفرق غير ملحوظ (أحدث سجلّ = سجلّ
     * اليوم نفسه). التصحيح البصري الثالث (صور مرجعية من يحيى) غيّر شكل
     * <x-recitation-range> نفسه من سطر نصّي متّصل ("— آية 1 إلى 20") إلى
     * بطاقة "من/إلى" مقسومة، كل طرف "السورة – رقم الآية" مستقلّ — التوكيد
     * هنا يطابق الشكل الجديد.
     */
    /** @test */
    public function the_dashboard_row_shows_todays_review_range_separately_from_the_lesson(): void
    {
        $surah = Surah::where('number', 2)->firstOrFail();
        $student = Student::create(['student_name' => 'طالب له مراجعة', 'teacher_id' => $this->teacher->id]);
        $student->recitationLogs()->create([
            'surah_id' => $surah->id, 'from_ayah' => 1, 'to_ayah' => 20, 'type' => 'مراجعة', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($surah->name.' – 1', false)
            ->assertSee($surah->name.' – 20', false);
    }

    /** @test */
    public function the_dashboard_row_shows_todays_attendance_status_as_a_badge(): void
    {
        $student = Student::create(['student_name' => 'طالب له حضور اليوم', 'teacher_id' => $this->teacher->id]);
        $student->attendances()->create(['date' => now()->toDateString(), 'status' => 'مستأذن']);

        // (S22) كانت "متأخر" قبل إلغائها من Attendance::STATUSES — استُبدلت
        // بـ"مستأذن" هنا فقط كقيمة صالحة أي حالة غير "حاضر"؛ التحقّق الحقيقي
        // من ظهور شارة اليوم تحديدًا هو سمة title التي لا تُكتَب إلا من هذه
        // الشارة نفسها.
        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('title="حضور اليوم"', false);
    }

    /**
     * (S24، الجزء الثالث — طلب صريح من يحيى بعد رؤية لوحة "قرآن" فعليًا):
     * "لو كان عند الطالب متن ليش يطلع المتن تحت اسم الطالب؟!" — شارة عدد
     * المتون المتتبَّعة حُذفت نهائيًا من `/dashboard`؛ للمتون لوحتها الخاصّة
     * الآن (`/poems`، راجع PoemBoardTest.php). Student::trackedPoemCountsFor()
     * نفسها لم تُمَسّ — لا تزال تغذّي عمود التصدير في التقارير.
     *
     * @test
     */
    public function the_dashboard_row_no_longer_shows_the_tracked_poem_count(): void
    {
        $poem = \App\Models\Poem::where('name', 'تحفة الأطفال')->firstOrFail();
        $student = Student::create(['student_name' => 'طالب له متن', 'teacher_id' => $this->teacher->id]);
        $student->poemRecitationLogs()->create([
            'poem_id' => $poem->id, 'to_bayt' => 10, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('1 متن');
    }
}
