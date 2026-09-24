<?php

namespace Tests\Feature;

use App\Models\Quarter;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * السجلّ الزمني (S7) — إضافة سطر جديد، حدود الآية، الملكية، التعديل (أيقونة
 * ✎، تصحيح صريح من يحيى)، والحذف.
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

        // النسبة موزونة بالأرباع الـ240 (S16) لا بعدد السور. البقرة هي آخر
        // خطوة في سلّم الحفظ، فأي سجلّ فيها — ولو جزئي — يُفعِّل الانسياب
        // التلقائي على كل ما بعدها في المصحف (آل عمران فصاعدًا)، بينما بقيّة
        // البقرة نفسها (بعد الآية 50) تبقى غير مغطّاة لأنها لم تُسجَّل فعليًا.
        // الربع 1 والربع 2 مغطّيان بالكامل (أول 50 آية من البقرة تتجاوزهما)،
        // الربع 3 جزئي بقدر ما تبقّى من الخمسين آية بعد بدايته، الأرباع من 4
        // إلى 19 صفر (لم تُسجَّل ولم تُنسَب بعد)، والربع 20 جزئي بقدر ما يقع
        // منه بعد بداية آل عمران (مغطّى بالانسياب)، وكل ما بعده حتى 240 كامل.
        $quarter3 = Quarter::where('quarter_number', 3)->firstOrFail();
        $quarter4 = Quarter::where('quarter_number', 4)->firstOrFail();
        $quarter20 = Quarter::where('quarter_number', 20)->firstOrFail();
        $quarter21 = Quarter::where('quarter_number', 21)->firstOrFail();

        $quarter3Length = $quarter4->start_global_ayah - $quarter3->start_global_ayah;
        $quarter20Length = $quarter21->start_global_ayah - $quarter20->start_global_ayah;

        $alImranStart = (int) Surah::where('number', '<', 3)->sum('ayah_count') + 1;

        $coveredInQuarter3 = 50 - $quarter3->start_ayah + 1;
        $coveredInQuarter20 = $quarter21->start_global_ayah - $alImranStart;

        $steps = ($quarter3->quarter_number - 1)
            + ($coveredInQuarter3 / $quarter3Length)
            + ($coveredInQuarter20 / $quarter20Length)
            + (Quarter::COUNT - $quarter20->quarter_number);

        $expected = round($steps / Quarter::COUNT * 100, 1);

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

    /**
     * (تصحيح صريح من يحيى، أيقونة تعديل ✎ بدل ✕ التراجع فقط): تعديل سجلّ
     * اليوم عبر PATCH — منفَّذ داخليًا كحذف+إنشاء (راجع تعليق
     * RecitationLogController::update())، فلا يُتوقَّع بقاء نفس المعرّف.
     */
    /** @test */
    public function a_teacher_can_edit_a_log_they_own(): void
    {
        $fatiha = Surah::where('number', 1)->firstOrFail();
        $baqarah = Surah::where('number', 2)->firstOrFail();
        $log = $this->student->recitationLogs()->create([
            'surah_id' => $fatiha->id, 'to_ayah' => 5, 'type' => 'حفظ',
            'status' => 'حافظ', 'logged_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->patchJson("/dashboard/{$this->student->student_id}/logs/{$log->id}", [
                'surah_id' => $baqarah->id,
                'to_ayah'  => 10,
                'type'     => 'حفظ',
                'status'   => 'غير حافظ',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('recitation_logs', ['id' => $log->id]);
        $this->assertDatabaseHas('recitation_logs', [
            'student_id' => $this->student->student_id,
            'surah_id'   => $baqarah->id,
            'to_ayah'    => 10,
            'status'     => 'غير حافظ',
        ]);
    }

    /**
     * (تصحيح فنيّ بعد فشل php artisan test عند يحيى): التوقّع الأصلي هنا كان
     * 403، لكن Student يحمل TeacherScope عامًا (راجع Student::booted())
     * يُخفي طلاب المعلّمين الآخرين كليًا عن الاستعلام — فـ
     * Student::findOrFail() داخل RecitationLogController::update() يفشل
     * بـ404 لمعلّم آخر قبل الوصول لسطر authorize('update', $log) أصلًا،
     * تمامًا كحال a_teacher_cannot_add_a_log_to_another_teachers_student()
     * أعلاه في هذا الملف (تتوقّع assertNotFound() للسبب نفسه بالضبط).
     * السلوك الأمني سليم ولم يتغيّر — الإصلاح هنا في توقّع الاختبار نفسه
     * ليطابق الاتفاقية القائمة أصلًا، لا في كود الإنتاج.
     */
    /** @test */
    public function a_teacher_cannot_edit_another_teachers_log(): void
    {
        $other = User::create([
            'name' => 'الأستاذ عمر', 'username' => 'omar_edit', 'password' => Hash::make('secret123'),
            'mosque' => 'جامع', 'classroom' => 'حلقة',
        ]);
        $fatiha = Surah::where('number', 1)->firstOrFail();
        $log = $this->student->recitationLogs()->create([
            'surah_id' => $fatiha->id, 'to_ayah' => 5, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($other)
            ->patchJson("/dashboard/{$this->student->student_id}/logs/{$log->id}", [
                'surah_id' => $fatiha->id, 'to_ayah' => 6, 'type' => 'حفظ',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('recitation_logs', ['id' => $log->id, 'to_ayah' => 5]);
    }

    /** @test */
    public function a_teacher_can_log_a_review_spanning_two_surahs(): void
    {
        // مراجعة عابرة لعدّة سور (S16): سطر واحد من آية في الملك إلى آية في
        // القلم (السورة التالية مباشرة)، بدل سطر منفصل لكل سورة.
        $mulk = Surah::where('number', 67)->firstOrFail();
        $qalam = Surah::where('number', 68)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'    => $mulk->id,
                'to_surah_id' => $qalam->id,
                'from_ayah'   => 1,
                'to_ayah'     => 5,
                'type'        => 'مراجعة',
                'logged_at'   => now()->toDateString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('recitation_logs', [
            'student_id'  => $this->student->student_id,
            'surah_id'    => $mulk->id,
            'to_surah_id' => $qalam->id,
            'to_ayah'     => 5,
        ]);
    }

    /** @test */
    public function a_surah_spanning_range_is_rejected_for_non_review_types(): void
    {
        $mulk = Surah::where('number', 67)->firstOrFail();
        $qalam = Surah::where('number', 68)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'    => $mulk->id,
                'to_surah_id' => $qalam->id,
                'to_ayah'     => 5,
                'type'        => 'حفظ',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_surah_id']);
    }

    /** @test */
    public function a_review_may_run_backward_from_a_later_surah_to_an_earlier_one(): void
    {
        // (تصحيح صريح من يحيى): "خلي الوضع فري سواء من فوق ولا من تحت" — لم
        // يعد يُفرَض ترتيب مصحفي بين سورتَي بداية/نهاية المراجعة، لأن الطالب
        // غالبًا يراجع من آخر ما حفظ رجوعًا (اسم الاختبار كان
        // the_end_surah_must_not_precede_the_start_surah_in_mushaf_order وكان
        // يتوقّع رفض هذا الاتجاه بالذات — الآن يُقبَل صراحةً).
        $qalam = Surah::where('number', 68)->firstOrFail();
        $mulk = Surah::where('number', 67)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'    => $qalam->id, // 68
                'to_surah_id' => $mulk->id,  // 67 — قبلها في المصحف، ومسموح الآن
                'to_ayah'     => 5,
                'type'        => 'مراجعة',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('recitation_logs', [
            'student_id'  => $this->student->student_id,
            'surah_id'    => $qalam->id,
            'to_surah_id' => $mulk->id,
            'to_ayah'     => 5,
        ]);
    }

    /** @test */
    public function from_ayah_cannot_exceed_the_starting_surahs_ayah_count(): void
    {
        $mulk = Surah::where('number', 67)->firstOrFail(); // 30 آية

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'  => $mulk->id,
                'from_ayah' => $mulk->ayah_count + 1,
                'to_ayah'   => $mulk->ayah_count + 1,
                'type'      => 'مراجعة',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_ayah']);
    }

    /** @test */
    public function to_ayah_is_checked_against_the_end_surahs_ayah_count_when_it_differs(): void
    {
        $mulk = Surah::where('number', 67)->firstOrFail();
        $qalam = Surah::where('number', 68)->firstOrFail();

        $this->actingAs($this->teacher)
            ->postJson("/dashboard/{$this->student->student_id}/logs", [
                'surah_id'    => $mulk->id,
                'to_surah_id' => $qalam->id,
                'to_ayah'     => $qalam->ayah_count + 1,
                'type'        => 'مراجعة',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_ayah']);
    }
}
