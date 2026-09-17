<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تعديل بيانات الطالب الأساسية (الاسم، الحلقة، الحالة).
 *
 * منذ S7 لم يعد update() يلمس السورة/الآية إطلاقًا — ذاك أصبح مسؤولية
 * RecitationLogController عبر السجلّ الزمني (انظر RecitationLogTest)، فاختبار
 * العطل القديم C-03 (تكرار السورة/الآية) لم يعد له معنى: لا حقل يتكرّر مقارنته
 * بنفسه بعد أن غاب من StudentController@update كليًّا.
 */
class StudentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ خالد',
            'username'     => 'khaled',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع الرحمة',
            'classroom' => 'حلقة المغرب',
        ]);

        $this->student = Student::create([
            'student_name' => 'يوسف',
            'teacher_id'   => $this->teacher->id,
        ]);
    }

    /** @test */
    public function a_teacher_can_rename_their_student(): void
    {
        $response = $this->actingAs($this->teacher)
            ->patchJson('/dashboard/'.$this->student->student_id, [
                'student_name' => 'يوسف عبدالرحمن',
            ]);

        $response->assertOk()->assertJsonPath('data.student_name', 'يوسف عبدالرحمن');

        $this->assertDatabaseHas('students', [
            'student_id'   => $this->student->student_id,
            'student_name' => 'يوسف عبدالرحمن',
        ]);
    }

    /** @test */
    public function a_teacher_cannot_touch_another_teachers_student(): void
    {
        $other = User::create([
            'name'      => 'الأستاذ ماجد',
            'username'     => 'majed',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع الفتح',
            'classroom' => 'حلقة العشاء',
        ]);

        $this->actingAs($other)
            ->patchJson('/dashboard/'.$this->student->student_id, [
                'student_name' => 'اسم مسروق',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('students', ['student_name' => 'يوسف']);
    }
}
