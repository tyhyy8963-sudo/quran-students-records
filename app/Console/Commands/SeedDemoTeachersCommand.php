<?php

namespace App\Console\Commands;

use App\Models\Circle;
use App\Models\Poem;
use App\Models\RecitationLog;
use App\Models\Student;
use App\Models\StudentPoemBaseline;
use App\Models\Surah;
use App\Models\User;
use App\Support\AccountPassword;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * بيانات تجريبية شاملة لفحص S15-S18 بصريًا (بعد طلب صاحب المنظومة).
 *
 * 6 معلّمين جدد × 20 طالبًا لكل واحد، متفاوتة عمدًا لا عشوائيًا: كل واحد من
 * العشرين "بروفايل" أدناه (PROFILE_COUNT) معرَّف صراحة ليغطّي زاوية مختلفة من
 * الفلاتر والشاشات الجديدة (نسبة تقدّم من صفر إلى مئة، حلقة/بلا حلقة، الحالات
 * الثلاث، مراجعة عادية وعابرة لسورتين، متون بأرضية فقط أو بسجلّات فعلية أو
 * قريبة من الاكتمال، وأنماط حضور تغطّي التنبيه ونظافته وحضور اليوم بكل قيمه
 * الأربع + "لم يُسجَّل"). نفس العشرين بروفايلًا تتكرّر على المعلّمين الستة —
 * التكرار عمدًا لا سهوًا: يضمن أن كل معلّم تجريبي يعرض نفس التغطية الكاملة،
 * بدل الاعتماد على عشوائية قد تفوّت حالة حافّة في تشغيلة بعينها.
 *
 * لا يحذف ولا يعدّل أي بيانات موجودة — يضيف فقط. يرفض العمل كليًا (لا جزئيًا)
 * لو كان أي من أسماء المستخدمين الستة المخطَّطة مستخدَمًا مسبقًا، حتى لا يُترك
 * النظام في حالة نصف مزروعة.
 */
class SeedDemoTeachersCommand extends Command
{
    protected $signature = 'keshf:seed-demo-teachers';

    protected $description = 'إضافة 6 معلّمين تجريبيين × 20 طالبًا لكل واحد ببيانات متفاوتة تغطي كل احتمالات الفلترة والعرض (S15-S18)؛ لا يحذف أي بيانات موجودة';

    private const TEACHER_USERNAMES = ['demo_t1', 'demo_t2', 'demo_t3', 'demo_t4', 'demo_t5', 'demo_t6'];

    private const TEACHER_NAMES = [
        'عبدالرحمن الشمري', 'فهد العتيبي', 'ماجد القحطاني', 'سلطان الدوسري', 'ناصر الغامدي', 'خالد الزهراني',
    ];

    private const MOSQUES = [
        'جامع الرحمة', 'جامع الفلاح', 'جامع التوحيد', 'جامع السلام', 'جامع النور', 'جامع الإيمان',
    ];

    private const CLASSROOMS = [
        'حلقة الفجر', 'حلقة العصر', 'حلقة المغرب', 'حلقة الفجر', 'حلقة العصر', 'حلقة المغرب',
    ];

    private const STUDENT_NAMES = [
        'عبدالله', 'محمد', 'أحمد', 'عمر', 'يوسف', 'إبراهيم', 'خالد', 'سعد', 'فيصل', 'تركي',
        'بندر', 'ماجد', 'نايف', 'سلمان', 'عبدالعزيز', 'طلال', 'راكان', 'مشعل', 'ريان', 'زياد',
    ];

    /**
     * عشرة مستويات تقدّم من صفر إلى مئة (null = بلا أي سجلّ حفظ إطلاقًا)،
     * كلٌّ منها رتبة سورة في ترتيب الحفظ المعكوس — الانسياب التلقائي
     * (MemorizationProgress) يكمل تلقائيًا كل ما قبلها، فسجلّ واحد يكفي.
     *
     * @var array<int, int|null>
     */
    private const PROGRESS_ORDERS = [null, 2, 12, 25, 38, 50, 63, 75, 90, 113];

    private array $grades = ['ممتاز', 'جيد جدا', 'جيد', 'مقبول'];

    public function handle(): int
    {
        $validator = Validator::make(
            ['usernames' => self::TEACHER_USERNAMES],
            ['usernames.*' => [Rule::unique('users', 'username')]]
        );

        if ($validator->fails()) {
            $this->error('بعض حسابات المعلّمين التجريبيين موجودة مسبقًا (demo_t1..demo_t6) — لن يُضاف شيء حتى لا تتكرّر البيانات. احذف الحسابات القديمة أولًا إن أردت إعادة الزرع.');

            return self::FAILURE;
        }

        $poems = Poem::orderBy('id')->get();
        $credentials = [];

        foreach (self::TEACHER_USERNAMES as $index => $username) {
            $credentials[] = DB::transaction(function () use ($index, $username, $poems) {
                return $this->seedTeacher($index, $username, $poems);
            });
        }

        $this->newLine();
        $this->info('تم إنشاء 6 معلّمين × 20 طالبًا. بيانات الدخول (تُعرض مرّة واحدة فقط):');
        $this->newLine();
        $this->table(['اسم المستخدم', 'كلمة المرور', 'الاسم', 'المسجد'], $credentials);

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string} صف لجدول بيانات الدخول
     */
    private function seedTeacher(int $index, string $username, \Illuminate\Support\Collection $poems): array
    {
        $name = self::TEACHER_NAMES[$index];
        $password = AccountPassword::generate();

        $teacher = User::create([
            'name'      => $name,
            'username'  => $username,
            'role'      => User::ROLE_TEACHER,
            'is_active' => true,
            'password'  => Hash::make($password),
            'mosque'    => self::MOSQUES[$index],
            'classroom' => self::CLASSROOMS[$index],
        ]);

        $circles = collect(['حلقة أ', 'حلقة ب', 'حلقة ج'])
            ->map(fn ($circleName) => Circle::create(['name' => $circleName, 'teacher_id' => $teacher->id]));

        foreach (self::STUDENT_NAMES as $i => $studentName) {
            $this->seedStudent($teacher, $circles, $poems, $i, $studentName);
        }

        return [$username, $password, $name, self::MOSQUES[$index]];
    }

    private function seedStudent(User $teacher, \Illuminate\Support\Collection $circles, \Illuminate\Support\Collection $poems, int $i, string $studentName): void
    {
        // حلقة دورية على 4 خيارات (3 حلقات + بلا حلقة) — يغطّي فلترة S18
        // المتعدّدة بما فيها خيار "بلا حلقة".
        $circle = match ($i % 4) {
            0 => $circles[0],
            1 => $circles[1],
            2 => $circles[2],
            default => null,
        };

        // أغلب الطلاب نشطون كواقع مدارس فعلي، مع أقلّية منقطعة/منتقلة تكفي
        // لفلترة الحالة المتعدّدة.
        $status = match (true) {
            $i === 7 => 'transferred',
            $i === 13 => 'inactive',
            default => 'active',
        };

        $student = Student::create([
            'student_name' => $studentName,
            'teacher_id'   => $teacher->id,
            'circle_id'    => $circle?->id,
            'status'       => $status,
        ]);

        $progressOrder = self::PROGRESS_ORDERS[$i % 10];
        $recency = match ($i % 3) {
            0 => now()->subDays(2),      // نشاط حديث (هذا الأسبوع)
            1 => now()->subDays(45),     // نشاط متوسّط (قبل شهر ونصف)
            default => now()->subDays(150), // نشاط قديم (قبل خمسة أشهر)
        };

        $targetSurah = null;

        if ($progressOrder !== null) {
            $targetSurah = Surah::where('memorization_order', $progressOrder)->first();
        }

        if ($targetSurah !== null) {
            $student->recitationLogs()->create([
                'surah_id'  => $targetSurah->id,
                'to_ayah'   => $targetSurah->ayah_count,
                'type'      => 'حفظ',
                'grade'     => $this->grades[$i % count($this->grades)],
                'logged_at' => $recency->toDateString(),
            ]);

            // نصف من له تقدّم فعلي يحصل أيضًا على سجلّ مراجعة — نصف هؤلاء
            // عابر لسورتين (S16) لفحص ذلك تحديدًا على بيانات حقيقية لا اختبار
            // آلي فقط.
            if ($i % 2 === 1) {
                $reviewTo = min(10, $targetSurah->ayah_count);
                $toSurahId = null;

                if ($i % 4 === 1) {
                    $nextSurah = Surah::where('number', $targetSurah->number + 1)->first();
                    if ($nextSurah !== null) {
                        $toSurahId = $nextSurah->id;
                        $reviewTo = min(5, $nextSurah->ayah_count);
                    }
                }

                $student->recitationLogs()->create([
                    'surah_id'    => $targetSurah->id,
                    'to_surah_id' => $toSurahId,
                    'from_ayah'   => 1,
                    'to_ayah'     => $reviewTo,
                    'type'        => 'مراجعة',
                    'grade'       => $this->grades[($i + 1) % count($this->grades)],
                    'logged_at'   => $recency->copy()->addDay()->toDateString(),
                ]);
            }
        }

        $this->seedPoems($student, $poems, $i, $recency);
        $this->seedAttendance($student, $i);
    }

    /**
     * تتبّع متون متفاوت (S15/S16): لا شيء لأغلب الطلاب (واقعي)، أرضية فقط
     * بلا سجلّات لبعضهم (يفحص طرح الأرضية كحدّ أدنى من أول نقطة في المنحنى)،
     * سجلّات فعلية جزئية لآخرين، ومتن مكتمل بالكامل (100%) لبعضهم (يفحص شارة
     * "أتمّ حفظ المتن" فعليًا لا تقريبًا).
     */
    private function seedPoems(Student $student, \Illuminate\Support\Collection $poems, int $i, Carbon $recency): void
    {
        if ($poems->isEmpty() || $i % 3 !== 0) {
            return;
        }

        $poem = $poems[$i % $poems->count()];

        if ($i % 6 === 0) {
            // أرضية فقط، بلا أي سجلّ فعلي.
            StudentPoemBaseline::create([
                'student_id'    => $student->student_id,
                'poem_id'       => $poem->id,
                'baseline_bayt' => max(1, intdiv($poem->bayt_count, 2)),
            ]);

            return;
        }

        // 1.0 كاملة لا "قريبة من الاكتمال" — فحص شارة "أتمّ حفظ المتن"
        // (Student::hasCompletedPoem) يحتاج نسبة 100% فعلية لا تقريبية.
        $fraction = $i % 12 === 3 ? 1.0 : 0.3;
        $toBayt = max(1, (int) round($poem->bayt_count * $fraction));

        $student->poemRecitationLogs()->create([
            'poem_id'   => $poem->id,
            'to_bayt'   => $toBayt,
            'type'      => 'حفظ',
            'logged_at' => $recency->toDateString(),
        ]);

        // متن ثانٍ لبعض هؤلاء تحديدًا — يفحص التتبّع المتوازي (أكثر من متن
        // معًا لنفس الطالب، S15).
        if ($i % 9 === 0 && $poems->count() > 1) {
            $secondPoem = $poems[($i + 1) % $poems->count()];
            $student->poemRecitationLogs()->create([
                'poem_id'   => $secondPoem->id,
                'to_bayt'   => max(1, intdiv($secondPoem->bayt_count, 5)),
                'type'      => 'حفظ',
                'logged_at' => $recency->toDateString(),
            ]);
        }
    }

    /**
     * أنماط حضور دورية على 5 حالات: انقطاعان متتاليان (يُشعل تنبيه S9)،
     * حضور متتابع (اليوم "حاضر")، شهر مختلط (اليوم "متأخر")، شهر مختلط آخر
     * (اليوم "مستأذن")، وبلا أي تسجيل إطلاقًا (اليوم "لم يُسجَّل بعد") — تغطّي
     * كل قيم فلتر حضور اليوم الجديد (S18) دفعة واحدة.
     */
    private function seedAttendance(Student $student, int $i): void
    {
        $today = now();

        switch ($i % 5) {
            case 0:
                $student->attendances()->create(['date' => $today->copy()->subDay()->toDateString(), 'status' => 'غائب']);
                $student->attendances()->create(['date' => $today->toDateString(), 'status' => 'غائب']);
                break;

            case 1:
                foreach (range(4, 0) as $daysAgo) {
                    $student->attendances()->create(['date' => $today->copy()->subDays($daysAgo)->toDateString(), 'status' => 'حاضر']);
                }
                break;

            case 2:
                foreach ([12, 9, 6, 3, 0] as $daysAgo) {
                    $status = $daysAgo === 0 ? 'متأخر' : ($daysAgo % 6 === 0 ? 'غائب' : 'حاضر');
                    $student->attendances()->create(['date' => $today->copy()->subDays($daysAgo)->toDateString(), 'status' => $status]);
                }
                break;

            case 3:
                foreach ([10, 7, 4, 0] as $daysAgo) {
                    $status = $daysAgo === 0 ? 'مستأذن' : 'حاضر';
                    $student->attendances()->create(['date' => $today->copy()->subDays($daysAgo)->toDateString(), 'status' => $status]);
                }
                break;

            default:
                // بلا أي تسجيل — "لم يُسجَّل بعد" في فلتر حضور اليوم.
                break;
        }
    }
}
