<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * السجلّ الزمني لحفظ/مراجعة متن (S15) — نسخة من RecitationLogTest بوحدة
 * "بيت" بدل "آية".
 */
class PoemRecitationLogTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Poem $poem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->poem = Poem::where('name', 'الجزرية')->firstOrFail(); // 107 بيتًا
    }

    /** @test */
    public function a_teacher_can_add_a_poem_log_and_it_updates_progress(): void
    {
        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/poem-logs", [
                'poem_id'   => $this->poem->id,
                'to_bayt'   => 50,
                'type'      => 'حفظ',
                'logged_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'progress_percent'], 'message']);

        $this->assertDatabaseHas('poem_recitation_logs', [
            'student_id' => $this->student->student_id,
            'poem_id'    => $this->poem->id,
            'to_bayt'    => 50,
        ]);
    }

    /** @test */
    public function the_bayt_cannot_exceed_the_poems_bayt_count(): void
    {
        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/poem-logs", [
                'poem_id' => $this->poem->id,
                'to_bayt' => 500,
                'type'    => 'حفظ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_bayt']);
    }

    /** @test */
    public function from_bayt_must_not_exceed_to_bayt(): void
    {
        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/poem-logs", [
                'poem_id'   => $this->poem->id,
                'from_bayt' => 20,
                'to_bayt'   => 10,
                'type'      => 'مراجعة',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_bayt']);
    }

    /** @test */
    public function a_teacher_cannot_add_a_poem_log_to_another_teachers_student(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other', 'password' => Hash::make('secret123'),
        ]);

        $this->actingAs($other)
            ->postJson("/dashboard/{$this->student->student_id}/poem-logs", [
                'poem_id' => $this->poem->id, 'to_bayt' => 1, 'type' => 'حفظ',
            ])
            ->assertNotFound();
    }

    /** @test */
    public function a_teacher_can_delete_a_poem_log_they_own(): void
    {
        $log = $this->student->poemRecitationLogs()->create([
            'poem_id' => $this->poem->id, 'to_bayt' => 5, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->deleteJson("/dashboard/{$this->student->student_id}/poem-logs/{$log->id}")
            ->assertOk();

        $this->assertDatabaseMissing('poem_recitation_logs', ['id' => $log->id]);
    }

    /** @test */
    public function a_teacher_cannot_delete_another_teachers_poem_log(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other2', 'password' => Hash::make('secret123'),
        ]);

        $log = $this->student->poemRecitationLogs()->create([
            'poem_id' => $this->poem->id, 'to_bayt' => 5, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        // نفس نمط RecitationLogController::destroy: Student::findOrFail
        // تحته TeacherScope، فيسقط الطلب على 404 قبل بلوغ فحص الصلاحية أصلًا —
        // لا 403.
        $this->actingAs($other)
            ->deleteJson("/dashboard/{$this->student->student_id}/poem-logs/{$log->id}")
            ->assertNotFound();
    }
}
