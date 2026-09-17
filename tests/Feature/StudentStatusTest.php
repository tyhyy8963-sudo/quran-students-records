<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * حذف حالة "متخرّج" واستبدالها بوسم محسوب Student::hasCompletedQuran() (S16).
 */
class StudentStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
    }

    /** @test */
    public function graduated_is_no_longer_a_valid_status(): void
    {
        $this->assertArrayNotHasKey('graduated', Student::STATUSES);

        $this->actingAs($this->teacher)
            ->patchJson('/dashboard/'.$this->student->student_id, [
                'student_name' => 'يوسف',
                'status'       => 'graduated',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function has_completed_quran_is_false_below_one_hundred_percent(): void
    {
        $this->student->recitationLogs()->create([
            'surah_id' => Surah::where('number', 114)->firstOrFail()->id,
            'to_ayah' => 6, 'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->assertFalse($this->student->hasCompletedQuran());
    }

    /** @test */
    public function has_completed_quran_is_true_at_one_hundred_percent_via_baseline(): void
    {
        // أرضية عند البقرة (الخطوة 113، الأخيرة) = أتمّ كل السلّم بلا سجلّات.
        $this->student->update([
            'quran_baseline_surah_id' => Surah::where('number', 2)->firstOrFail()->id,
        ]);

        $this->assertTrue($this->student->fresh()->hasCompletedQuran());
    }
}
