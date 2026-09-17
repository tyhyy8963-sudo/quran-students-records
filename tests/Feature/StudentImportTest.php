<?php

namespace Tests\Feature;

use App\Models\Circle;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * استيراد الطلاب من ملف CSV (S11).
 */
class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name'      => 'الأستاذ حمد',
            'username'     => 'hamad',
            'password'  => Hash::make('secret123'),
            'mosque'    => 'جامع الهدى',
            'classroom' => 'حلقة الفجر',
        ]);
    }

    private function csvFile(string $content, string $name = 'students.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** @test */
    public function it_imports_students_from_a_csv_file_skipping_the_header_row(): void
    {
        $csv = "اسم الطالب,الحلقة\nسعيد,\nمنصور,\n";

        $response = $this->actingAs($this->teacher)
            ->post('/dashboard/import', ['file' => $this->csvFile($csv)]);

        $response->assertOk()->assertJsonPath('data.created', 2);

        $this->assertDatabaseHas('students', ['student_name' => 'سعيد', 'teacher_id' => $this->teacher->id]);
        $this->assertDatabaseHas('students', ['student_name' => 'منصور', 'teacher_id' => $this->teacher->id]);
    }

    /** @test */
    public function it_matches_students_to_an_existing_circle_by_name(): void
    {
        $circle = Circle::create(['name' => 'حلقة العصر', 'teacher_id' => $this->teacher->id]);
        $csv = "اسم الطالب,الحلقة\nبدر,حلقة العصر\n";

        $this->actingAs($this->teacher)
            ->post('/dashboard/import', ['file' => $this->csvFile($csv)])
            ->assertOk();

        $this->assertDatabaseHas('students', [
            'student_name' => 'بدر',
            'circle_id'    => $circle->id,
        ]);
    }

    /** @test */
    public function an_unknown_circle_name_adds_the_student_without_a_circle_and_notes_it(): void
    {
        $csv = "اسم الطالب,الحلقة\nطارق,حلقة غير موجودة\n";

        $response = $this->actingAs($this->teacher)
            ->post('/dashboard/import', ['file' => $this->csvFile($csv)]);

        $response->assertOk()->assertJsonPath('data.created', 1);
        $this->assertNotEmpty($response->json('data.notes'));

        $this->assertDatabaseHas('students', ['student_name' => 'طارق', 'circle_id' => null]);
    }

    /** @test */
    public function rows_without_a_name_are_skipped_without_stopping_the_rest_of_the_file(): void
    {
        $csv = "اسم الطالب,الحلقة\n,حلقة العصر\nياسين,\n";

        $response = $this->actingAs($this->teacher)
            ->post('/dashboard/import', ['file' => $this->csvFile($csv)]);

        $response->assertOk()->assertJsonPath('data.created', 1);
        $this->assertDatabaseHas('students', ['student_name' => 'ياسين']);
        $this->assertDatabaseCount('students', 1);
    }

    /** @test */
    public function a_non_csv_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('students.pdf', 10, 'application/pdf');

        $this->actingAs($this->teacher)
            ->postJson('/dashboard/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function imported_students_are_scoped_to_the_importing_teacher(): void
    {
        $csv = "اسم الطالب\nغيث\n";

        $this->actingAs($this->teacher)
            ->post('/dashboard/import', ['file' => $this->csvFile($csv)])
            ->assertOk();

        $student = Student::first();
        $this->assertEquals($this->teacher->id, $student->teacher_id);
    }
}
