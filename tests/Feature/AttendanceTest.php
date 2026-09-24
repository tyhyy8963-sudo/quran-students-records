<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * الحضور (S9) — الشاشة السريعة (حفظ دفعة)، منع التسجيل المستقبلي، الملكية.
 * مؤشّر الانقطاع البسيط (S9) كان يظهر على لوحة "قرآن" أيضًا — حُذف من هناك في
 * S24 (طلب صريح من يحيى)، لا يزال يغذّي قائمة الانتباه في لوحة التقارير فقط.
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ سعد',
            'username'     => 'saad',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع السلام',
            'classroom' => 'حلقة الفجر',
        ]);

        $this->student = Student::create(['student_name' => 'عمر', 'teacher_id' => $this->teacher->id]);
    }

    /** @test */
    public function a_teacher_can_mark_attendance_for_their_students(): void
    {
        $this->actingAs($this->teacher)
            ->postJson('/attendance', [
                'date' => now()->toDateString(),
                'entries' => [
                    ['student_id' => $this->student->student_id, 'status' => 'حاضر'],
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['date', 'count'], 'message']);

        // لا نقارن عمود "date" مباشرة في assertDatabaseHas: كاست "date" على
        // الموديل يمرّر القيمة عبر fromDateTime عند الإسناد، فتُخزَّن على
        // SQLite (بيئة الاختبار) بصيغة تاريخ ووقت كاملة رغم إرسال تاريخ فقط
        // — تفصيل تخزين داخلي لا يؤثّر على القراءة عبر whereDate()، فهو
        // ليس عطلًا يستحق اختباره هنا.
        $this->assertTrue(
            Attendance::where('student_id', $this->student->student_id)
                ->where('status', 'حاضر')
                ->whereDate('date', now()->toDateString())
                ->exists()
        );
    }

    /** @test */
    public function marking_the_same_day_twice_updates_the_existing_row_instead_of_duplicating(): void
    {
        $date = now()->toDateString();

        $this->actingAs($this->teacher)->postJson('/attendance', [
            'date' => $date,
            'entries' => [['student_id' => $this->student->student_id, 'status' => 'حاضر']],
        ])->assertOk();

        $this->actingAs($this->teacher)->postJson('/attendance', [
            'date' => $date,
            'entries' => [['student_id' => $this->student->student_id, 'status' => 'غائب بدون عذر']],
        ])->assertOk();

        $this->assertEquals(
            1,
            Attendance::where('student_id', $this->student->student_id)->whereDate('date', $date)->count()
        );
        $this->assertTrue(
            Attendance::where('student_id', $this->student->student_id)
                ->where('status', 'غائب بدون عذر')
                ->whereDate('date', $date)
                ->exists()
        );
    }

    /** @test */
    public function future_dates_are_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->postJson('/attendance', [
                'date' => now()->addDay()->toDateString(),
                'entries' => [['student_id' => $this->student->student_id, 'status' => 'حاضر']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function an_unknown_status_is_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->postJson('/attendance', [
                'date' => now()->toDateString(),
                'entries' => [['student_id' => $this->student->student_id, 'status' => 'مريض']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.status']);
    }

    /** @test */
    public function a_teacher_cannot_mark_attendance_for_another_teachers_student(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other_attendance', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);

        $this->actingAs($other)
            ->postJson('/attendance', [
                'date' => now()->toDateString(),
                'entries' => [['student_id' => $this->student->student_id, 'status' => 'حاضر']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.student_id']);
    }

    /**
     * (S24، الجزء الثالث — طلب صريح من يحيى بعد رؤية لوحة "قرآن" فعليًا):
     * شارة "انقطاع" (غياب جلستَين متتاليتَين) كانت تظهر تحت اسم الطالب في
     * `/dashboard` — يحيى: "حالة الطالب... المفترض هنا مش مكانها". حُذفت
     * نهائيًا من هذه اللوحة. `Attendance::alertsFor()` نفسها لم تُمَسّ — لا
     * تزال تغذّي "قائمة الانتباه" في لوحة التقارير (`ReportController::
     * index()`)، فهذا الاختبار يتحقّق فقط من غيابها عن `/dashboard` تحديدًا،
     * لا من إلغاء الميزة كليًا من النظام.
     *
     * @test
     */
    public function two_consecutive_absences_no_longer_show_an_alert_badge_on_the_dashboard(): void
    {
        $this->student->attendances()->create(['date' => now()->subDays(2), 'status' => 'غائب']);
        $this->student->attendances()->create(['date' => now()->subDay(), 'status' => 'غائب']);

        $this->actingAs($this->teacher)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('انقطاع');
    }

    /** @test */
    public function the_student_page_shows_the_attendance_calendar_for_the_recorded_month(): void
    {
        $recordedDate = now()->startOfMonth()->addDays(4);
        $this->student->attendances()->create(['date' => $recordedDate, 'status' => 'غائب']);

        $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}")
            ->assertOk()
            ->assertSee('data-status="غائب"', false);
    }

    /**
     * انحدار: كاست "date" يخزّن وقتًا كاملاً خلف التاريخ على SQLite، وكانت
     * الشاشة السريعة تقارن نص التاريخ الخام (where بدل whereDate) فتفشل
     * مطابقة سطر اليوم نفسه دائمًا — الحالة تظهر فارغة رغم وجود تسجيل فعلي.
     *
     * (تصحيح S23): /attendance عادت لعرض فقط — لا أزرار قابلة للنقر بعد
     * الآن، فالتحقّق هنا صار من ظهور حالة اليوم المسجَّلة كنصّ/شارة بدل زرّ
     * محدَّد سلفًا، لكنه نفس جوهر الانحدار المقصود: عطل كاست "date" يظهر
     * بنفس الطريقة في whereDate('date', $date) داخل AttendanceController::index().
     */
    /** @test */
    public function the_read_only_attendance_screen_shows_the_already_recorded_status_for_today(): void
    {
        // (S22) كانت "متأخر" قبل إلغائها من Attendance::STATUSES — أي حالة
        // غير "حاضر" ما زالت تثبت نفس السلوك المقصود هنا.
        $this->student->attendances()->create(['date' => now(), 'status' => 'مستأذن']);

        $this->actingAs($this->teacher)
            ->get('/attendance')
            ->assertOk()
            ->assertSee('data-status="مستأذن"', false);
    }

    /**
     * انحدار مماثل: تقويم الشهر كان يستخدم whereBetween على نص التاريخ
     * الخام، فيستثني سطر آخر يوم في الشهر (قيمته المخزَّنة "أكبر" نصيًا من
     * حدّ النهاية بلا وقت).
     */
    /** @test */
    public function the_calendar_includes_the_last_day_of_the_month(): void
    {
        $lastDay = now()->endOfMonth()->startOfDay();
        $this->student->attendances()->create(['date' => $lastDay, 'status' => 'غائب']);

        $this->actingAs($this->teacher)
            ->get("/dashboard/{$this->student->student_id}")
            ->assertOk()
            ->assertSee('data-status="غائب"', false);
    }
}
