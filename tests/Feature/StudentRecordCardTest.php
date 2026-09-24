<?php

namespace Tests\Feature;

use App\Models\Poem;
use App\Models\Student;
use App\Models\Surah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * بطاقة سجلّ الطالب (S15) — عرض آخر موضع مراجعة وقسم المتون.
 *
 * (S24 — بطلب صريح من يحيى): كانت هذه الوثيقة تشرح لماذا تبقى "أرضية متن"
 * يدوية بخلاف أرضية حفظ القرآن (التي أُلغيت والاعتماد فيها كليًا على الانسياب
 * التلقائي من السجلّات الفعلية، راجع MemorizationProgress::applyCascade()):
 * كان القرار السابق أن المتون متوازية التتبّع بلا سلّم ترتيب واحد مشترك،
 * فلا أساس تلقائي مماثل يُبنى عليه. طلب يحيى صراحةً معاملة أرضية المتن الآن
 * مثل أرضية القرآن تمامًا — أُلغيت الأرضية اليدوية نهائيًا (الجدول والنموذج
 * والمسار)، والاعتماد كليًا على مديات from_bayt..to_bayt الفعلية المسجَّلة
 * (راجع PoemProgress). اختبارات ضبط الأرضية عبر الواجهة حُذفت من هذا الملف.
 */
class StudentRecordCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacher(string $username = 'khaled'): User
    {
        return User::create([
            'name' => 'الأستاذ خالد', 'username' => $username, 'password' => Hash::make('secret123'),
            'mosque' => 'جامع الرحمة', 'classroom' => 'حلقة المغرب',
        ]);
    }

    private function surah(int $number): Surah
    {
        return Surah::where('number', $number)->firstOrFail();
    }

    /** @test */
    public function the_show_page_lists_a_tracked_poem_with_its_percentage_and_last_position(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        $poem = Poem::where('name', 'الجزرية')->firstOrFail();

        $student->poemRecitationLogs()->create([
            'poem_id' => $poem->id, 'to_bayt' => 50, 'type' => 'حفظ', 'logged_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('الجزرية', false)
            ->assertSee('آخر حفظ: بيت 50', false);
    }

    /** @test */
    public function the_show_page_shows_the_last_review_position_separately_from_memorization(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        $baqarah = $this->surah(2);
        $nas = $this->surah(114);

        $student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'to_ayah' => 50, 'type' => 'حفظ', 'logged_at' => now(),
        ]);
        $student->recitationLogs()->create([
            'surah_id' => $nas->id, 'from_ayah' => 1, 'to_ayah' => 6, 'type' => 'مراجعة', 'logged_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('آخر موضع: البقرة', false)
            ->assertSee('آخر مراجعة: الناس', false);
    }

    /** @test */
    public function a_student_with_no_tracked_poems_shows_the_empty_poems_message(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('لا متون مُتتبَّعة بعد', false);
    }

    /** @test */
    public function the_show_page_flags_a_student_who_completed_the_quran(): void
    {
        $teacher = $this->makeTeacher();
        $student = Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);
        $baqarah = $this->surah(2); // الخطوة 113 — تسجيلها كاملة يكمل السلّم بأسره بالانسياب التلقائي (S15)

        $student->recitationLogs()->create([
            'surah_id' => $baqarah->id, 'from_ayah' => 1, 'to_ayah' => $baqarah->ayah_count,
            'type' => 'حفظ', 'logged_at' => now()->toDateString(),
        ]);

        $this->actingAs($teacher)
            ->get("/dashboard/{$student->student_id}")
            ->assertOk()
            ->assertSee('أتمّ حفظ القرآن', false);

        // الحالة تبقى نشط، لا "متخرّج" (S16): الوسم منفصل عن status كليًا.
        $this->assertSame('active', $student->fresh()->status);
    }
}
