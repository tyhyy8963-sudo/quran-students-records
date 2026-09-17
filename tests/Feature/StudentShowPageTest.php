<?php

namespace Tests\Feature;

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
}
