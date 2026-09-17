<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * اختبارات سبرنت 4: تحصين الخادم.
 *
 * تغطّي ثلاثة أمور لا يغطّيها StudentUpdateTest:
 *  - StudentPolicy كخط دفاع صريح عند الحذف والاسترجاع (B-04).
 *  - الحذف الناعم وإخفاء المحذوف عن اللوحة والاسترجاع لاحقًا (B-05).
 *  - قواعد التحقق في StoreStudentRequest/UpdateStudentRequest (B-04).
 */
class StudentSprint4Test extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(string $username = 'khaled'): User
    {
        return User::create([
            'name'      => 'الأستاذ خالد',
            'username'  => $username,
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع الرحمة',
            'classroom' => 'حلقة المغرب',
        ]);
    }

    /** @test */
    public function deleting_a_student_soft_deletes_and_hides_it_from_the_listing(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->deleteJson('/dashboard/'.$student->student_id)
            ->assertOk()
            ->assertJsonPath('data.id', $student->student_id);

        // السجلّ باقٍ في القاعدة مع تعبئة deleted_at، لا حذف فعلي (B-05).
        $this->assertSoftDeleted('students', ['student_id' => $student->student_id]);

        // ويختفي عن اللوحة الافتراضية دون الحاجة إلى شرط يدوي في المتحكّم.
        $this->assertFalse(
            Student::whereKey($student->student_id)->exists(),
            'يجب ألا يظهر الطالب المحذوف في الاستعلام الافتراضي.'
        );
    }

    /** @test */
    public function a_teacher_can_restore_their_own_soft_deleted_student(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        $student->delete();

        $this->actingAs($teacher)
            ->postJson('/dashboard/'.$student->student_id.'/restore')
            ->assertOk()
            ->assertJsonPath('data.id', $student->student_id);

        $this->assertDatabaseHas('students', [
            'student_id' => $student->student_id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function a_teacher_cannot_delete_another_teachers_student(): void
    {
        $owner  = $this->makeTeacher('owner');
        $intruder = $this->makeTeacher('intruder');
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $owner->id]);

        // النطاق يخفي الطالب أصلًا عن المعلّم الآخر، فتكون النتيجة 404
        // (وليس 403) — هذا هو دفاع TeacherScope في الطبقة الأولى.
        $this->actingAs($intruder)
            ->deleteJson('/dashboard/'.$student->student_id)
            ->assertNotFound();

        $this->assertDatabaseHas('students', [
            'student_id' => $student->student_id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function the_policy_blocks_restoring_another_teachers_student_even_without_the_scope(): void
    {
        $owner    = $this->makeTeacher('owner2');
        $intruder = $this->makeTeacher('intruder2');
        $student  = Student::create(['student_name' => 'سارة', 'teacher_id' => $owner->id]);
        $student->delete();

        // withTrashed()->findOrFail() في restore() يتجاوز فلترة الحذف الناعم
        // لكن ليس عزل المعلّم (TeacherScope يبقى فعّالًا)، فتُرفض المحاولة.
        $this->actingAs($intruder)
            ->postJson('/dashboard/'.$student->student_id.'/restore')
            ->assertNotFound();

        $this->assertSoftDeleted('students', ['student_id' => $student->student_id]);
    }

    /** @test */
    public function creating_a_student_returns_a_full_resource_with_a_default_status(): void
    {
        // انتباه: هذا الطريق (نجاح الإنشاء) لا يمرّ أبدًا باستعلام findOrFail()
        // إضافي كما في update/restore — الاستجابة تُبنى من النموذج في الذاكرة
        // مباشرة بعد Student::create(). لو بقيت قيمة status الافتراضية على
        // مستوى العمود فقط (الهجرة) دون أن تنعكس على النموذج نفسه، تنهار
        // StudentResource::statusLabel() هنا تحديدًا رغم نجاح كل اختبارات
        // update/restore الأخرى.
        $teacher = $this->makeTeacher();
        $fatiha = Surah::where('number', 1)->firstOrFail();

        $this->actingAs($teacher)
            ->postJson('/dashboard/create', [
                'student_name' => 'طالب جديد',
                'surah_id'     => $fatiha->id,
                'the_ayah'     => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.student_name', 'طالب جديد')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.status_label', 'نشط')
            ->assertJsonPath('data.current_position.surah_name', 'الفاتحة');
    }

    /** @test */
    public function creating_a_student_requires_a_name(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)
            ->postJson('/dashboard/create', [
                'student_name' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_name']);
    }

    /** @test */
    public function the_ayah_number_must_be_at_least_one(): void
    {
        $teacher = $this->makeTeacher();
        $fatiha = Surah::where('number', 1)->firstOrFail();

        $this->actingAs($teacher)
            ->postJson('/dashboard/create', [
                'student_name' => 'طالب جديد',
                'surah_id'     => $fatiha->id,
                'the_ayah'     => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['the_ayah']);
    }

    /** @test */
    public function the_ayah_number_cannot_exceed_the_surahs_ayah_count(): void
    {
        $teacher = $this->makeTeacher();
        $fatiha = Surah::where('number', 1)->firstOrFail(); // 7 آيات فقط

        $this->actingAs($teacher)
            ->postJson('/dashboard/create', [
                'student_name' => 'طالب جديد',
                'surah_id'     => $fatiha->id,
                'the_ayah'     => 8,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['the_ayah']);
    }

    /** @test */
    public function updating_status_rejects_a_value_outside_the_allowed_list(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->patchJson('/dashboard/'.$student->student_id, [
                'student_name' => 'يوسف',
                'status'       => 'not-a-real-status',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function updating_status_to_an_allowed_value_persists_and_reflects_in_the_resource(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->patchJson('/dashboard/'.$student->student_id, [
                'student_name' => 'يوسف',
                'status'       => 'graduated',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'graduated')
            ->assertJsonPath('data.status_label', 'متخرّج');

        $this->assertDatabaseHas('students', [
            'student_id' => $student->student_id,
            'status'     => 'graduated',
        ]);
    }

    /** @test */
    public function a_guest_cannot_reach_any_student_endpoint(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        // طلب JSON بلا جلسة: معالج الاستثناءات في Laravel يعيد 401 بدل
        // إعادة توجيه لصفحة الدخول (وهو السلوك المناسب لواجهة تعتمد fetch).
        $this->getJson('/dashboard')->assertStatus(401);
        $this->postJson('/dashboard/create', ['student_name' => 'أحد'])->assertStatus(401);
        $this->deleteJson('/dashboard/'.$student->student_id)->assertStatus(401);
    }
}
