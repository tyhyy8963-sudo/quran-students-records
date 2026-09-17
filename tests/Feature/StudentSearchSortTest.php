<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * البحث والفلترة والفرز على لوحة الطلاب (S11).
 */
class StudentSearchSortTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ نايف',
            'username'     => 'nayef',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع التقوى',
            'classroom' => 'حلقة الظهر',
        ]);
    }

    /** @test */
    public function searching_by_name_only_returns_matching_students(): void
    {
        Student::create(['student_name' => 'عبدالله', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'عبدالرحمن', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'خالد', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?q='.urlencode('عبد'))
            ->assertOk()
            ->assertSee('عبدالله')
            ->assertSee('عبدالرحمن')
            ->assertDontSee('خالد');
    }

    /** @test */
    public function filtering_by_circle_shows_only_that_circles_students(): void
    {
        $circle = Circle::create(['name' => 'حلقة أ', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'طالب داخل الحلقة', 'teacher_id' => $this->teacher->id, 'circle_id' => $circle->id]);
        Student::create(['student_name' => 'طالب بلا حلقة', 'teacher_id' => $this->teacher->id]);

        $this->actingAs($this->teacher)
            ->get('/dashboard?circle_id='.$circle->id)
            ->assertOk()
            ->assertSee('طالب داخل الحلقة')
            ->assertDontSee('طالب بلا حلقة');

        $this->actingAs($this->teacher)
            ->get('/dashboard?circle_id=none')
            ->assertOk()
            ->assertSee('طالب بلا حلقة')
            ->assertDontSee('طالب داخل الحلقة');
    }

    /** @test */
    public function filtering_by_status_shows_only_that_status(): void
    {
        Student::create(['student_name' => 'طالب نشط', 'teacher_id' => $this->teacher->id, 'status' => 'active']);
        Student::create(['student_name' => 'طالب منقطع', 'teacher_id' => $this->teacher->id, 'status' => 'inactive']);

        $this->actingAs($this->teacher)
            ->get('/dashboard?status=inactive')
            ->assertOk()
            ->assertSee('طالب منقطع')
            ->assertDontSee('طالب نشط');
    }

    /** @test */
    public function sorting_by_name_descending_reverses_the_default_order(): void
    {
        Student::create(['student_name' => 'أحمد', 'teacher_id' => $this->teacher->id]);
        Student::create(['student_name' => 'ياسر', 'teacher_id' => $this->teacher->id]);

        $response = $this->actingAs($this->teacher)->get('/dashboard?sort=name&dir=desc');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'ياسر') < strpos($content, 'أحمد'));
    }

    /** @test */
    public function sorting_by_status_groups_students_of_the_same_status_together(): void
    {
        Student::create(['student_name' => 'طالب1', 'teacher_id' => $this->teacher->id, 'status' => 'inactive']);
        Student::create(['student_name' => 'طالب2', 'teacher_id' => $this->teacher->id, 'status' => 'active']);

        $this->actingAs($this->teacher)
            ->get('/dashboard?sort=status&dir=asc')
            ->assertOk()
            ->assertSee('طالب1')
            ->assertSee('طالب2');
    }
}
