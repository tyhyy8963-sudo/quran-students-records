<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** صفحة سجلّ الطالب (S8) — تصيير الصفحة، مؤشر التقدّم، وحماية الملكية. */
class StudentShowPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(string $username = 'khaled'): User
    {
        return User::create([
            'name' => 'الأستاذ خالد', 'username' => $username, 'password' => Hash::make('secret123'),
            'mosque' => 'جامع الرحمة', 'classroom' => 'حلقة المغرب',
        ]);
    }

    /** @test */
    public function the_page_renders_with_no_logs_yet(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('يوسف', false)
            ->assertSee('لم يبدأ الحفظ بعد', false)
            ->assertSee('0%', false);
    }

    /** @test */
    public function the_page_shows_the_latest_position_and_progress(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        $baqarah = Surah::where('number', 2)->firstOrFail();

        $student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'to_ayah' => 50, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('البقرة', false)
            ->assertSee('آية 50', false);
    }

    /** @test */
    public function a_teacher_cannot_view_another_teachers_student_page(): void
    {
        $owner = $this->makeTeacher('owner');
        $intruder = $this->makeTeacher('intruder');
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $owner->id]);

        $this->actingAs($intruder)
            ->get("/dashboard/{$student->student_id}")
            ->assertNotFound();
    }

    /**
     * (طلب صريح من يحيى): زرّا "تعديل" (اسم الطالب + الحلقة معًا) و"حذف
     * الطالب" انتقلا إلى هنا من صفّ اللوحة الرئيسية — راجع تعليقَي الزرّين
     * في dashboard.blade.php من التصحيح الثالث. تحقّق فقط من ظهور الزرّين
     * وقائمة حلقات المعلّم نفسه في نموذج التعديل — السلوك الفعلي للحفظ
     * والحذف مُختبَر مسبقًا في StudentUpdateTest وStudentSprint4Test عبر
     * نفس نقطتَي النهاية (PATCH/DELETE) اللتين يستهلكهما هذا الزرّان فقط.
     */
    /** @test */
    public function the_show_page_offers_an_edit_and_delete_button_with_the_teachers_own_circles(): void
    {
        $teacher = $this->makeTeacher();
        $otherTeacher = $this->makeTeacher('other');
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        Circle::create(['name' => 'الفرقان', 'teacher_id' => $teacher->id]);
        Circle::create(['name' => 'حلقة ليست لي', 'teacher_id' => $otherTeacher->id]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('id="editStudentBtn"', false)
            ->assertSee('id="deleteStudentBtn"', false)
            ->assertSee('id="editStudentForm"', false)
            ->assertSee('الفرقان')
            ->assertDontSee('حلقة ليست لي');
    }
}
