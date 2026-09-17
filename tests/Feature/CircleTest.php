<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * الحلقات (S6) — عزل بين المعلّمين، منع تكرار الاسم لنفس المعلّم، وحذف
 * حلقة لا يحذف طلابها.
 */
class CircleTest extends TestCase
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
    public function a_teacher_can_create_a_circle(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)
            ->postJson('/circles', ['name' => 'حلقة الفجر'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'حلقة الفجر');

        $this->assertDatabaseHas('circles', ['teacher_id' => $teacher->id, 'name' => 'حلقة الفجر']);
    }

    /** @test */
    public function two_different_teachers_can_use_the_same_circle_name(): void
    {
        // طلب HTTP واحد فقط عمدًا: طلبان بـ actingAs() مختلفين في نفس
        // الاختبار يصطدمان بوسيط auth.session (يقارن بصمة كلمة مرور الجلسة
        // بين طلب وآخر ضمن نفس التطبيق المُختبَر) فيُسجَّل خروج تلقائي —
        // ليست هذه حالة تحدث في الاستخدام الحقيقي (كل معلّم بمتصفّح مستقل).
        $a = $this->makeTeacher('a');
        Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $a->id]);

        $b = $this->makeTeacher('b');
        $this->actingAs($b)
            ->postJson('/circles', ['name' => 'حلقة الفجر'])
            ->assertCreated();

        $this->assertEquals(2, Circle::withoutGlobalScopes()->where('name', 'حلقة الفجر')->count());
    }

    /** @test */
    public function a_teacher_cannot_reuse_their_own_circle_name(): void
    {
        $teacher = $this->makeTeacher();
        Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->postJson('/circles', ['name' => 'حلقة الفجر'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function deleting_a_circle_does_not_delete_its_students(): void
    {
        $teacher = $this->makeTeacher();
        $circle = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $teacher->id]);
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id, 'circle_id' => $circle->id]);

        $this->actingAs($teacher)
            ->deleteJson("/circles/{$circle->id}")
            ->assertOk();

        $this->assertDatabaseMissing('circles', ['id' => $circle->id]);
        $this->assertDatabaseHas('students', ['student_id' => $student->student_id, 'circle_id' => null]);
    }

    /** @test */
    public function a_teacher_cannot_delete_another_teachers_circle(): void
    {
        $owner = $this->makeTeacher('owner');
        $intruder = $this->makeTeacher('intruder');
        $circle = Circle::create(['name' => 'حلقة الفجر', 'teacher_id' => $owner->id]);

        $this->actingAs($intruder)
            ->deleteJson("/circles/{$circle->id}")
            ->assertNotFound();
    }
}
