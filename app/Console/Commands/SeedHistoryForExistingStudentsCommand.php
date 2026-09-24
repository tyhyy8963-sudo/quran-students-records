<?php

namespace App\Console\Commands;

use App\Models\Poem;
use App\Models\Student;
use App\Models\Surah;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * إغناء تاريخ الطلاب الموجودين فعليًا (بعد ملاحظة صاحب المنظومة أن بيانات
 * seed-demo-teachers الأولى كانت سطرًا واحدًا لكل طالب — لا يكفي لرؤية منحنى
 * حقيقي في الشاشات الجديدة S16). يضيف فقط، لا يُنشئ معلّمين ولا طلابًا جددًا
 * ولا يحذف أي سطر موجود — يعمل على *كل* طالب موجود حاليًا (لأيّ معلّم)، بصرف
 * النظر عن مصدره (زُرع تجريبيًا أو أُدخل يدويًا).
 *
 * ═══ القرار: طالب بلا أي سجلّ حفظ يبقى بلا سجلّات ═══
 * "طالب انضمّ للتوّ بلا أي نشاط مسجَّل بعد" حالة واقعية بذاتها، لا نقصًا في
 * البيانات — تلفيق تاريخ له يُفقد هذه الحالة من التغطية بدل أن يضيف تنوّعًا.
 * نفس المنطق لأرضية متن بلا سجلّات فعلية: الأرضية تمثّل ما سبق الانضمام، لا
 * جلسات، فتُترك كما هي (راجع تعليق PoemProgress::timeline()).
 *
 * ═══ الحماية من التكرار عند إعادة التشغيل ═══
 * طالب له 4 سجلّات "حفظ" فأكثر يُعتبر مُغنى مسبقًا (هذا الأمر نفسه أو نشاط
 * حقيقي كافٍ) فيُتخطّى — إعادة تشغيل الأمر بالخطأ لا تضاعف البيانات إلى ما لا
 * نهاية. نفس المبدأ لكل متن على حدة (3 سجلّات فأكثر لنفس المتن = مُغنى).
 */
class SeedHistoryForExistingStudentsCommand extends Command
{
    protected $signature = 'keshf:seed-history';

    protected $description = 'إضافة تاريخ حفظ/مراجعة/متون يمتدّ شهرين فأكثر لكل طالب موجود له نشاط أصلًا (لا يُنشئ طلابًا أو معلّمين جددًا، ولا يحذف أي سجلّ)';

    private array $grades = ['ممتاز', 'جيد جدا', 'جيد', 'مقبول'];

    public function handle(): int
    {
        $students = Student::withoutGlobalScopes()->get();

        if ($students->isEmpty()) {
            $this->warn('لا يوجد أي طالب في القاعدة حاليًا — لا شيء لإغنائه.');

            return self::SUCCESS;
        }

        $poems = Poem::orderBy('id')->get();

        $bar = $this->output->createProgressBar($students->count());
        $bar->start();

        $enrichedMemorization = 0;
        $enrichedPoems = 0;

        foreach ($students as $student) {
            DB::transaction(function () use ($student, $poems, &$enrichedMemorization, &$enrichedPoems) {
                if ($this->enrichMemorization($student)) {
                    $enrichedMemorization++;
                }

                $enrichedPoems += $this->enrichPoems($student, $poems);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("تم فحص {$students->count()} طالبًا: أُغني تاريخ الحفظ/المراجعة لـ{$enrichedMemorization}، وأُغني {$enrichedPoems} متنًا متتبَّعًا (عبر كل الطلاب).");

        return self::SUCCESS;
    }

    /**
     * @return bool  true لو أُضيف تاريخ فعليًا لهذا الطالب
     */
    private function enrichMemorization(Student $student): bool
    {
        $existingLogs = $student->recitationLogs()
            ->where('type', 'حفظ')
            ->whereNotNull('surah_id')
            ->get();

        // 4 فأكثر = مُغنى مسبقًا (هذا الأمر أو نشاط حقيقي كافٍ) — تخطٍّ يمنع
        // التضاعف عند إعادة التشغيل.
        if ($existingLogs->count() >= 4 || $existingLogs->isEmpty()) {
            return false;
        }

        $furthestOrder = 0;
        foreach ($existingLogs as $log) {
            $surah = Surah::find($log->surah_id);
            if ($surah !== null && $surah->memorization_order !== null) {
                $furthestOrder = max($furthestOrder, $surah->memorization_order);
            }
        }

        if ($furthestOrder < 2) {
            // لا مجال واقعي لبناء محطّات وسيطة قبل رتبة 2.
            return false;
        }

        $stepsCount = min(6, $furthestOrder);
        $checkpoints = $this->spreadIntegers(1, $furthestOrder, $stepsCount);
        $daysAgo = $this->spreadDaysAgo(count($checkpoints));

        foreach ($checkpoints as $idx => $order) {
            $surah = Surah::where('memorization_order', $order)->first();

            if ($surah === null) {
                continue;
            }

            $student->recitationLogs()->create([
                'surah_id'  => $surah->id,
                'to_ayah'   => $surah->ayah_count,
                'type'      => 'حفظ',
                'grade'     => $this->grades[$idx % count($this->grades)],
                'logged_at' => now()->subDays($daysAgo[$idx])->toDateString(),
            ]);
        }

        // مراجعات متنوّعة عبر آخر شهرين، من داخل ما يُفترض أنه محفوظ فعلًا
        // (رتبة ≤ أبعد رتبة وصلها) — نصفها عابر لسورتين (S16) عمدًا.
        $reviewCandidates = Surah::whereNotNull('memorization_order')
            ->where('memorization_order', '<=', $furthestOrder)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        foreach ($reviewCandidates as $idx => $surah) {
            $toSurahId = null;
            $toAyah = min(10, $surah->ayah_count);

            if ($idx % 2 === 0) {
                $next = Surah::where('number', $surah->number + 1)->first();
                if ($next !== null) {
                    $toSurahId = $next->id;
                    $toAyah = min(5, $next->ayah_count);
                }
            }

            $student->recitationLogs()->create([
                'surah_id'    => $surah->id,
                'to_surah_id' => $toSurahId,
                'from_ayah'   => 1,
                'to_ayah'     => $toAyah,
                'type'        => 'مراجعة',
                'grade'       => $this->grades[($idx + 2) % count($this->grades)],
                'logged_at'   => now()->subDays(random_int(3, 65))->toDateString(),
            ]);
        }

        return true;
    }

    /**
     * @return int  عدد المتون التي أُغني تاريخها لهذا الطالب
     */
    private function enrichPoems(Student $student, Collection $poems): int
    {
        $enriched = 0;

        foreach ($poems as $poem) {
            $existing = $student->poemRecitationLogs()
                ->where('poem_id', $poem->id)
                ->where('type', 'حفظ')
                ->get();

            // لا يتتبّع هذا المتن أصلًا، أو يتتبّعه بأرضية فقط بلا سجلّات فعلية
            // (الأرضية تمثّل ما قبل الانضمام عمدًا — لا تُلفَّق لها جلسات).
            if ($existing->isEmpty()) {
                continue;
            }

            // 3 فأكثر لنفس المتن = مُغنى مسبقًا.
            if ($existing->count() >= 3) {
                continue;
            }

            $currentMax = (int) $existing->max('to_bayt');

            if ($currentMax < 3) {
                continue;
            }

            $fractions = [0.3, 0.6, 0.9];
            $daysAgo = [55, 28, 8];

            foreach ($fractions as $idx => $fraction) {
                $toBayt = max(1, (int) round($currentMax * $fraction));

                $student->poemRecitationLogs()->create([
                    'poem_id'   => $poem->id,
                    'to_bayt'   => $toBayt,
                    'type'      => 'حفظ',
                    'logged_at' => now()->subDays($daysAgo[$idx])->toDateString(),
                ]);
            }

            $enriched++;
        }

        return $enriched;
    }

    /**
     * $count عدد صحيح تصاعدي موزَّع بالتساوي تقريبًا بين $min و$max شاملَين
     * (بلا تكرار) — يبني "محطّات" وسيطة معقولة بدل قيم عشوائية قد تتقارب أو
     * تتجاوز الحدّ.
     *
     * @return array<int, int>
     */
    private function spreadIntegers(int $min, int $max, int $count): array
    {
        if ($count <= 1) {
            return [$max];
        }

        $values = [];

        for ($i = 0; $i < $count; $i++) {
            $ratio = $i / ($count - 1);
            $values[] = (int) round($min + $ratio * ($max - $min));
        }

        return array_values(array_unique($values));
    }

    /**
     * عدد أيام مضت لكل محطّة، من الأقدم (نحو 70 يومًا — أكثر من شهرين) إلى
     * الأحدث (نحو 3 أيام)، بحيث تُبنى محطّات الحفظ زمنيًا بترتيب صحيح (الأقدم
     * منطقيًا يجب أن يحمل رتبة أدنى، وهذا مضمون هنا لأن spreadIntegers تصاعدية
     * والأيام هنا تنازلية بنفس عدد العناصر).
     *
     * @return array<int, int>
     */
    private function spreadDaysAgo(int $count): array
    {
        if ($count <= 1) {
            return [10];
        }

        $oldest = 70;
        $newest = 3;
        $days = [];

        for ($i = 0; $i < $count; $i++) {
            $ratio = $i / ($count - 1);
            $days[] = (int) round($oldest - $ratio * ($oldest - $newest));
        }

        return $days;
    }
}
