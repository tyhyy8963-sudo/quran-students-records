<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\Student;
use App\Models\StudentPoemBaseline;
use App\Models\User;
use App\Support\PoemProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * أرضية متن لطالب (S16) — نفس فكرة أرضية الحفظ (max لا تلفيق سجلّات).
 */
class PoemBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Poem $poem;
    private PoemProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
        $this->poem = Poem::where('name', 'تحفة الأطفال')->firstOrFail(); // 61 بيتًا
        $this->progress = app(PoemProgress::class);
    }

    /** @test */
    public function a_baseline_raises_coverage_with_no_real_logs(): void
    {
        StudentPoemBaseline::create([
            'student_id' => $this->student->student_id,
            'poem_id'    => $this->poem->id,
            'baseline_bayt' => 30,
        ]);

        $this->assertSame(30, $this->progress->coverage($this->student, $this->poem));
        $this->assertSame(round(30 / 61 * 100, 1), $this->progress->percentage($this->student, $this->poem));
    }

    /** @test */
    public function a_real_log_above_the_baseline_is_not_capped(): void
    {
        StudentPoemBaseline::create([
            'student_id' => $this->student->student_id,
            'poem_id'    => $this->poem->id,
            'baseline_bayt' => 30,
        ]);

        $this->student->poemRecitationLogs()->create([
            'poem_id' => $this->poem->id, 'to_bayt' => 61, 'from_bayt' => 1,
            'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);
        $this->progress->forget($this->student, $this->poem);

        $this->assertSame(61, $this->progress->coverage($this->student, $this->poem));
    }

    /** @test */
    public function a_real_log_below_the_baseline_does_not_lower_it(): void
    {
        StudentPoemBaseline::create([
            'student_id' => $this->student->student_id,
            'poem_id'    => $this->poem->id,
            'baseline_bayt' => 30,
        ]);

        $this->student->poemRecitationLogs()->create([
            'poem_id' => $this->poem->id, 'to_bayt' => 10, 'from_bayt' => 1,
            'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);
        $this->progress->forget($this->student, $this->poem);

        $this->assertSame(30, $this->progress->coverage($this->student, $this->poem));
    }
}
