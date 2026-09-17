<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use App\Support\MemorizationProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * أرضية الحفظ (S16) — "آخر سورة أتمّها الطالب قبل الانضمام" كحدّ أدنى
 * حسابي في MemorizationProgress، لا كسجلّات مُلفَّقة.
 */
class QuranBaselineTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private MemorizationProgress $progress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'الأستاذ خالد', 'username' => 'khaled', 'password' => Hash::make('secret123'),
        ]);

        $this->progress = app(MemorizationProgress::class);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    /** @test */
    public function a_baseline_raises_the_percentage_with_no_real_logs(): void
    {
        // النصر هي الخطوة الخامسة في تسلسل الحفظ (114..110) — أرضية عندها
        // تعني: أتمّ الطالب خمس خطوات قبل الانضمام، بلا أي سجلّ.
        $student = Student::create([
            'student_name' => 'يوسف', 'teacher_id' => $this->teacher->id,
            'quran_baseline_surah_id' => $this->surah(110)->id,
        ]);

        $this->assertEqualsWithDelta(5 / 113 * 100, $student->progressPercentage(), 0.05);
        $this->assertCount(5, $this->progress->completedSurahs($student));
    }

    /** @test */
    public function a_real_log_above_the_baseline_is_not_capped_or_doubled(): void
    {
        $student = Student::create([
            'student_name' => 'يوسف', 'teacher_id' => $this->teacher->id,
            'quran_baseline_surah_id' => $this->surah(110)->id, // الخطوة 5
        ]);

        // سجلّ حقيقي فوق الأرضية: البقرة (الخطوة 113) نصفها.
        $student->recitationLogs()->create([
            'surah_id' => $this->surah(2)->id, 'to_ayah' => 143, 'from_ayah' => 1,
            'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);
        $this->progress->forget($student);

        $expected = round((5 + 143 / 286) / \App\Models\Surah::COUNTABLE_COUNT * 100, 1);

        $this->assertSame($expected, $student->progressPercentage());
    }

    /** @test */
    public function a_real_log_within_the_baseline_range_does_not_double_count(): void
    {
        $student = Student::create([
            'student_name' => 'يوسف', 'teacher_id' => $this->teacher->id,
            'quran_baseline_surah_id' => $this->surah(110)->id, // الخطوة 5، تشمل الناس
        ]);

        // سجلّ حفظ فعلي لسورة داخلة في الأرضية أصلًا — لا يرفع الرقم فوق ما
        // تعطيه الأرضية وحدها (max لا جمع).
        $student->recitationLogs()->create([
            'surah_id' => $this->surah(114)->id, 'to_ayah' => 6, 'from_ayah' => 1,
            'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);
        $this->progress->forget($student);

        $this->assertEqualsWithDelta(5 / 113 * 100, $student->progressPercentage(), 0.05);
    }

    /** @test */
    public function students_without_a_baseline_are_unaffected(): void
    {
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $this->teacher->id]);

        $this->assertSame(0.0, $student->progressPercentage());
    }

    /** @test */
    public function warm_for_applies_the_baseline_the_same_way_as_a_single_lookup(): void
    {
        $withBaseline = Student::create([
            'student_name' => 'يوسف', 'teacher_id' => $this->teacher->id,
            'quran_baseline_surah_id' => $this->surah(110)->id,
        ]);
        $withoutBaseline = Student::create(['student_name' => 'محمد', 'teacher_id' => $this->teacher->id]);

        $this->progress->warmFor(collect([$withBaseline, $withoutBaseline]));

        $this->assertEqualsWithDelta(5 / 113 * 100, $this->progress->percentage($withBaseline), 0.05);
        $this->assertSame(0.0, $this->progress->percentage($withoutBaseline));
    }
}
