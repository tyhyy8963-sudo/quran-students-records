<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * تبويب "السجلات" (S26) — أُعيد كتابة هذا الملف بالكامل (2026-09-23) بعد أن
 * وضّح يحيى أن المحاولة الأولى (قائمة زمنية موحَّدة لحفظ+مراجعة+متون+حضور)
 * كانت فهمًا خاطئًا لمقصده. المطلوب الفعلي **دليل بسيط بكل الطلاب**: اسم كل
 * طالب + رابط "سجلّ" يفتح صفحته الكاملة (students.show) + إمكانية إضافة
 * طالب جديد من نفس الصفحة. الفلترة (بحث + حلقة) لم تتغيّر — راجع
 * RecordsController للتفصيل الكامل.
 */
class RecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'     => 'الأستاذ فهد',
            'username' => 'fahad',
            'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function guests_cannot_view_the_records_page(): void
    {
        $this->get('/records')->assertRedirect('/login');
    }

    /** @test */
    public function it_lists_every_student_with_a_link_to_their_full_record(): void
    {
        $student = Student::create(['student_name' => 'خالد', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/records');

        $response->assertOk();
        $response->assertSee('خالد');
        $response->assertSee(route('students.show', $student->student_id), false);
    }

    /** @test */
    public function records_page_does_not_list_another_teachers_students(): void
    {
        $otherTeacher = User::create([
            'name' => 'الأستاذ آخر', 'username' => 'otherrec', 'password' => Hash::make('secret123'),
        ]);
        Student::create(['student_name' => 'طالب آخر', 'teacher_id' => $otherTeacher->id]);
        Student::create(['student_name' => 'خالد', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/records');

        $response->assertSee('خالد');
        $response->assertDontSee('طالب آخر');
    }

    /** @test */
    public function searching_by_student_name_filters_the_list(): void
    {
        Student::create(['student_name' => 'خالد', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'سعيد', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/records?q=خالد');

        $response->assertSee('خالد');
        $response->assertDontSee('سعيد');
    }

    /** @test */
    public function circle_filter_narrows_the_list_to_that_circle(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        $circleB = Circle::create(['name' => 'حلقة ب', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'من حلقة أ', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'من حلقة ب', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleB->id]);

        $response = $this->actingAs($this->teacher)->get('/records?circle_id[]='.$circleA->id);

        $response->assertSee('من حلقة أ');
        $response->assertDontSee('من حلقة ب');
    }

    /** @test */
    public function circle_id_none_shows_only_students_without_a_circle(): void
    {
        $circleA = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'له حلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circleA->id]);
        Student::create(['student_name' => 'بلا حلقة', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/records?circle_id[]=none');

        $response->assertSee('بلا حلقة');
        $response->assertDontSee('له حلقة');
    }

    /** @test */
    public function an_empty_records_page_shows_the_empty_state_not_an_error(): void
    {
        $response = $this->actingAs($this->teacher)->get('/records');

        $response->assertOk();
        $response->assertSee('لا طلاب بعد');
    }

    /**
     * "من هناك يكون إضافة طالب جديد" — طلب يحيى الصريح: الصفحة تتيح إضافة
     * طالب جديد مباشرة، لا فقط تصفّح من أضيفوا مسبقًا من صفحة أخرى.
     */
    /** @test */
    public function the_page_offers_a_button_to_add_a_new_student(): void
    {
        $response = $this->actingAs($this->teacher)->get('/records');

        $response->assertOk();
        $response->assertSee('addStudentBtn', false);
        $response->assertSee('+ إضافة طالب');
    }

    /**
     * حارس ارتداد لعطل ترقيم الصفحات الحقيقي المُصلَح في custom.blade.php
     * (راجع تعليق ذلك الملفّ للتفصيل الكامل) — أي صفحة تتجاوز حجم صفحة واحدة
     * يجب أن تُحمَّل صفحتها الثانية بلا عطل.
     */
    /** @test */
    public function a_second_page_of_students_renders_the_pagination_links_without_error(): void
    {
        for ($i = 0; $i < 55; $i++) {
            Student::create(['student_name' => "طالب رقم {$i}", 'teacher_id' => $this->teacher->id]);
        }

        $firstPage = $this->actingAs($this->teacher)->get('/records');
        $firstPage->assertOk();
        $firstPage->assertSee('page=2', false);

        $secondPage = $this->actingAs($this->teacher)->get('/records?page=2');
        $secondPage->assertOk();
    }
}
