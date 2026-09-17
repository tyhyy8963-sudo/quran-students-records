<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * السجلّ الزمني (S7) — إضافة سطر جديد، حدود الآية، الملكية، والحذف
 * ("تراجع") فور الإضافة.
 */
class RecitationLogTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ خالد',
            'username'  => 'khaled',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع الرحمة',
            'classroom' => 'حلقة المغرب',
        ]);

        $this->student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);
    }

    /** @test */
    public function a_teacher_can_add_a_recitation_log_and_it_updates_progress(): void
    {
        $baqarah = Surah::where('number', 2)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'  => $baqarah->id,
                'to_ayah'   => 50,
                'type'      => 'حفظ',
                'logged_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'progress_percent'], 'message']);

        $this->assertDatabaseHas('recitation_logs', [
            'student_id' => $this->student->student_id,
            'surah_id'   => $baqarah->id,
            'to_ayah'    => 50,
        ]);

        // النسبة بترتيب الحفظ المعكوس (S14): البقرة خطوة واحدة من 113، وقد
        // حُفظ منها 50 آية من 286 — أي جزء من خطوة، لا 0.9% كما كان بحساب
        // ترتيب المصحف القديم (7 آيات فاتحة + 50 من 6236).
        $expected = round((50 / 286) / \App\Models\Surah::COUNTABLE_COUNT * 100, 1);

        $this->assertEquals($expected, $this->student->fresh()->progressPercentage());
    }

    /** @test */
    public function the_ayah_cannot_exceed_the_surahs_ayah_count(): void
    {
        $fatiha = Surah::where('number', 1)->firstOrFail(); // 7 آيات

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id' => $fatiha->id,
                'to_ayah'  => 9,
                'type'     => 'حفظ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_ayah']);
    }

    /** @test */
    public function from_ayah_must_not_exceed_to_ayah(): void
    {
        $baqarah = Surah::where('number', 2)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'  => $baqarah->id,
                'from_ayah' => 20,
                'to_ayah'   => 10,
                'type'      => 'مراجعة',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_ayah']);
    }

    /** @test */
    public function a_review_log_does_not_change_the_students_current_position(): void
    {
        $baqarah = Surah::where('number', 2)->firstOrFail();
        $fatiha = Surah::where('number', 1)->firstOrFail();

        $this->student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'to_ayah' => 50, 'type' => 'حفظ', 'logged_at' => now()->subDay(),
        ]);

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id' => $fatiha->id,
                'to_ayah'  => 7,
                'type'     => 'مراجعة',
            ])
            ->assertCreated();

        // "مراجعة" لا تغيّر الموضع — يبقى آخر "حفظ" هو المرجع.
        $log = $this->student->fresh()->latestMemorizationLog;
        $this->assertEquals($baqarah->id, $log->surah_id);
    }

    /** @test */
    public function a_teacher_cannot_add_a_log_to_another_teachers_student(): void
    {
        $other = User::create([
            'name' => 'أستاذ آخر', 'username' => 'other', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);
        $fatiha = Surah::where('number', 1)->firstOrFail();

        $this->actingAs($other)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id' => $fatiha->id, 'to_ayah' => 1, 'type' => 'حفظ',
            ])
            ->assertNotFound();
    }

    /** @test */
    public function a_teacher_can_delete_a_log_they_own(): void
    {
        $fatiha = Surah::where('number', 1)->firstOrFail();
        $log = $this->student->recitationLogs()->create([
            'surah_id' => $fatiha->id, 'to_ayah' => 5, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->deleteJson("/dashboard/{$this->student->student_id}/logs/{$log->id}")
            ->assertOk();

        $this->assertDatabaseMissing('recitation_logs', ['id' => $log->id]);
    }
}
