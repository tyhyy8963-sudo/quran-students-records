<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Poem;
use App\Models\PoemRecitationLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * لوحة طلاب خاصة بالمتون (S24، الجزء الثاني — طلب صريح من يحيى: "لوحة طلاب
 * خاصة بالمتون"). أُعيدت الفلترة بالكامل بطلب لاحق صريح (2026-09-23): لم تعد
 * اللوحة مقيّدة بتبويب متن واحد إجباري؛ فلاتر متعدّدة الاختيار (متن + حلقة)
 * ونطاق نسبة تقدّم (progress_min/progress_max)، وصفّ واحد لكل طالب تظهر فيه
 * شارة منفصلة بنسبته لكل متن يتتبّعه ضمن فلتر المتن. راجع PoemBoardController
 * للتفصيل الكامل.
 */
class PoemBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Poem $poem;      // تحفة الأطفال — 61 بيتًا
    private Poem $otherPoem; // الجزرية — 107 أبيات

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ سعيد',
            'username'  => 'saeed',
            'password'  => Hash::make('secret123'),
        ]);

        $this->poem = Poem::where('name', 'تحفة الأطفال')->firstOrFail();
        $this->otherPoem = Poem::where('name', 'الجزرية')->firstOrFail();
    }

    private function trackPoem(Student $student, Poem $poem = null, int $toBayt = 10): PoemRecitationLog
    {
        return PoemRecitationLog::create([
            'student_id' => $student->student_id,
            'poem_id'    => ($poem ?? $this->poem)->id,
            'to_bayt'    => $toBayt,
            'type'       => 'حفظ',
            'logged_at'  => now()->toDateString(),
        ]);
    }

    /** @test */
    public function guests_cannot_view_the_poem_board(): void
    {
        $this->get('/poems')->assertRedirect('/login');
    }

    /** @test */
    public function only_students_who_actually_track_the_selected_poem_are_listed(): void
    {
        $tracking = Student::create(['student_name' => 'متتبِّع', 'teacher_id' => $this->teacher->id]);
        $notTracking = Student::create(['student_name' => 'غير متتبِّع', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($tracking);

        $response = $this->actingAs($this->teacher)->get('/poems?poem_id='.$this->poem->id);

        $response->assertOk();
        $response->assertSee('متتبِّع');
        $response->assertDontSee('غير متتبِّع');
    }

    /** @test */
    public function the_board_only_shows_the_current_teachers_students(): void
    {
        $otherTeacher = User::create([
            'name' => 'الأستاذ آخر', 'username' => 'other', 'password' => Hash::make('secret123'),
        ]);

        $mine = Student::create(['student_name' => 'طالبي', 'teacher_id' => $this->teacher->id]);
        $theirs = Student::create(['student_name' => 'طالب آخر', 'teacher_id' => $otherTeacher->id]);
        $this->trackPoem($mine);
        $this->trackPoem($theirs);

        $response = $this->actingAs($this->teacher)->get('/poems?poem_id='.$this->poem->id);

        $response->assertSee('طالبي');
        $response->assertDontSee('طالب آخر');
    }

    /** @test */
    public function selecting_a_circle_narrows_the_tracked_students_further(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        $circleB = Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);

        $inA = Student::create(['student_name' => 'من حلقة أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        $inB = Student::create(['student_name' => 'من حلقة ب', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleB->id]);
        $this->trackPoem($inA);
        $this->trackPoem($inB);

        $response = $this->actingAs($this->teacher)
            ->get('/poems?poem_id='.$this->poem->id.'&circle_id='.$circleA->id);

        $response->assertSee('من حلقة أ');
        $response->assertDontSee('من حلقة ب');
    }

    /** @test */
    public function circle_id_none_shows_only_students_without_a_circle(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);

        $withCircle = Student::create(['student_name' => 'له حلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        $withoutCircle = Student::create(['student_name' => 'بلا حلقة', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($withCircle);
        $this->trackPoem($withoutCircle);

        $response = $this->actingAs($this->teacher)
            ->get('/poems?poem_id='.$this->poem->id.'&circle_id=none');

        $response->assertSee('بلا حلقة');
        $response->assertDontSee('له حلقة');
    }

    /** @test */
    public function no_poem_id_filter_shows_students_tracking_any_poem(): void
    {
        $student = Student::create(['student_name' => 'طالب أول', 'teacher_id' => $this->teacher->id]);
        $otherStudent = Student::create(['student_name' => 'طالب ثانٍ', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->poem);
        $this->trackPoem($otherStudent, $this->otherPoem);

        // بلا poem_id إطلاقًا = "عرض الكل" (بطلب صريح من يحيى)، لا الرجوع
        // تلقائيًا لأول متن أبجديًا كما كان سلوك اللوحة قبل 2026-09-23.
        $response = $this->actingAs($this->teacher)->get('/poems');

        $response->assertOk();
        $response->assertSee('طالب أول');
        $response->assertSee('طالب ثانٍ');
    }

    /** @test */
    public function a_student_tracking_a_different_poem_is_not_listed_under_this_one(): void
    {
        $student = Student::create(['student_name' => 'متتبّع متن آخر', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->otherPoem);

        $response = $this->actingAs($this->teacher)->get('/poems?poem_id='.$this->poem->id);

        $response->assertDontSee('متتبّع متن آخر');
    }

    /** @test */
    public function selecting_multiple_poems_shows_students_tracking_either_one(): void
    {
        $tracksFirst = Student::create(['student_name' => 'يتابع الأول', 'teacher_id' => $this->teacher->id]);
        $tracksSecond = Student::create(['student_name' => 'يتابع الثاني', 'teacher_id' => $this->teacher->id]);
        $tracksNeither = Student::create(['student_name' => 'لا يتابع شيئًا منهما', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($tracksFirst, $this->poem);
        $this->trackPoem($tracksSecond, $this->otherPoem);

        $response = $this->actingAs($this->teacher)
            ->get('/poems?poem_id[]='.$this->poem->id.'&poem_id[]='.$this->otherPoem->id);

        $response->assertSee('يتابع الأول');
        $response->assertSee('يتابع الثاني');
        $response->assertDontSee('لا يتابع شيئًا منهما');
    }

    /** @test */
    public function a_student_tracking_two_poems_shows_a_separate_percentage_badge_for_each(): void
    {
        $student = Student::create(['student_name' => 'متعدّد المتون', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($student, $this->poem, 10);      // 10/61 → 16.4%
        $this->trackPoem($student, $this->otherPoem, 10); // 10/107 → 9.3%

        $response = $this->actingAs($this->teacher)
            ->get('/poems?poem_id[]='.$this->poem->id.'&poem_id[]='.$this->otherPoem->id);

        $response->assertSee('متعدّد المتون');
        $response->assertSee('16.4%', false);
        $response->assertSee('9.3%', false);
    }

    /**
     * تصحيح واجهة (2026-09-23) — بعد أن أبلغ يحيى أن الفلترة القديمة
     * (تبويبات) كانت أحلى وأن طلبه كان إضافات لا استبدالًا كاملًا، أُعيدت
     * تبويبات المتن والحلقة (نفس آلية dashboard.blade.php) بجانب قوائم
     * <details> المتقدّمة، لا بدلًا عنها. هذان الاختباران يتحقّقان من ظهور
     * التبويبات نفسها — الفلترة الفعلية عبر poem_id/circle_id (المستعمَلة من
     * كلا الواجهتين) مُختبَرة أصلًا في بقية هذا الملفّ.
     */
    /** @test */
    public function the_page_shows_a_quick_tab_for_every_poem_and_every_circle(): void
    {
        $circle = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/poems');

        $response->assertOk();
        // تبويبات المتن: رابط سريع بنقرة واحدة لكل متن (poem_id بصيغة مفردة،
        // لا مصفوفة — نفس صيغة تبويبات الحلقة في dashboard.blade.php).
        $response->assertSee('poem_id='.$this->poem->id, false);
        $response->assertSee('poem_id='.$this->otherPoem->id, false);
        // تبويبات الحلقة: نفس الفكرة.
        $response->assertSee('circle_id='.$circle->id, false);
    }

    /** @test */
    public function the_all_tab_is_active_by_default_with_no_filters(): void
    {
        $response = $this->actingAs($this->teacher)->get('/poems');

        $response->assertOk();
        // كلا صفّي التبويبات (متن وحلقة) يبدآن بتبويب "الكل" النشط افتراضًا
        // حين لا يوجد أي فلتر — يظهر مرّتين (متن وحلقة معًا).
        $this->assertSame(
            2,
            substr_count($response->getContent(), 'circle-tab active"')
        );
    }

    /**
     * استيراد من ملف (طلب صريح من يحيى 2026-09-23: "ضيف زر استيراد من ملف
     * excel أو csv في صفحة المتون كمان") — نفس زرّ ومسار الاستيراد الموجودين
     * أصلًا في dashboard.blade.php، يظهر حتى بلا أي متون بعد.
     */
    /** @test */
    public function the_page_offers_an_import_button_even_with_no_poems_tracked(): void
    {
        $response = $this->actingAs($this->teacher)->get('/poems');

        $response->assertOk();
        $response->assertSee('importStudentsBtn', false);
        $response->assertSee('استيراد من ملف (CSV أو Excel)');
    }

    /** @test */
    public function progress_range_filter_only_shows_students_with_a_poem_percentage_in_range(): void
    {
        $highProgress = Student::create(['student_name' => 'تقدّمه عالٍ', 'teacher_id' => $this->teacher->id]);
        $lowProgress = Student::create(['student_name' => 'تقدّمه منخفض', 'teacher_id' => $this->teacher->id]);
        $this->trackPoem($highProgress, $this->poem, 55); // 55/61 → 90.2%
        $this->trackPoem($lowProgress, $this->poem, 5);   // 5/61 → 8.2%

        $response = $this->actingAs($this->teacher)
            ->get('/poems?poem_id='.$this->poem->id.'&progress_min=50');

        $response->assertSee('تقدّمه عالٍ');
        $response->assertDontSee('تقدّمه منخفض');
    }
}
