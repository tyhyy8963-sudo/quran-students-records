<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ترحيل البيانات إلى السجلّ الزمني (S7) — الهجرة الأخطر في الخطة.
 *
 * كل سطر في student_data يتحوّل إلى أول سجلّ "حفظ" في recitation_logs لنفس
 * الطالب، بتاريخ إنشاء سجلّ الطالب نفسه (أفضل تقدير متاح لتاريخ حقيقي لم
 * يُسجَّل من قبل). هذا يشمل الأسطر التي فشلت مطابقة اسم سورتها في الهجرة
 * السابقة (S6): بدل حذف نص "اسم غريب" الأصلي بصمت مع الجدول، يُحفَظ داخل
 * الملاحظة نفسها (surah_id فارغ) ليراه المعلّم في الخط الزمني ويصحّحه يدويًا
 * — لا شاشة تسوية مستقلة (خارج نطاق هذا السبرنت)، لكن لا فقدان بيانات أيضًا.
 * طالب أُضيف أصلًا بلا أي سطر student_data لا يُنشأ له سجلّ — لا قيمة من
 * سجلّ فارغ لا يمثّل شيئًا قِيل فعلًا.
 */
return new class extends Migration
{
    private const MARKER = 'سجلّ مرحَّل من النسخة السابقة (قبل S7)';

    public function up()
    {
        // كل طالب له سطر student_data واحد دائمًا (كان يُنشأ تلقائيًا مع
        // الطالب حتى بلا سورة/آية) — لا نُنشئ سجلًّا لمن لم يكن له surah_id
        // ولا legacy_surah_text أصلًا: لا قيمة من سجلّ "حفظ" لموضع لم يُذكَر قط.
        $source = DB::table('student_data')
            ->join('students', 'students.student_id', '=', 'student_data.student_id')
            ->where(function ($q) {
                $q->whereNotNull('student_data.surah_id')
                    ->orWhereNotNull('student_data.legacy_surah_text');
            })
            ->select(
                'student_data.student_id',
                'student_data.surah_id',
                'student_data.legacy_surah_text',
                'student_data.the_ayah',
                'students.created_at'
            )
            ->get();

        $now = now();
        $rows = $source->map(function ($r) use ($now) {
            $note = $r->surah_id !== null
                ? self::MARKER.' — لا تاريخ حفظ حقيقي متاح.'
                : self::MARKER.' — تعذّر التعرّف على اسم السورة الأصلي: "'.$r->legacy_surah_text.'". صحِّح السورة يدويًا بإضافة سجلّ جديد، ثم يمكن حذف هذا السجلّ المؤقت.';

            return [
                'student_id' => $r->student_id,
                'surah_id'   => $r->surah_id,
                'from_ayah'  => null,
                'to_ayah'    => $r->the_ayah ?? 1,
                'type'       => 'حفظ',
                'grade'      => null,
                'notes'      => $note,
                'logged_at'  => $r->created_at ? date('Y-m-d', strtotime($r->created_at)) : now()->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values()->all();

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('recitation_logs')->insert($chunk);
            }

            $expected = count($rows);
            $actual = DB::table('recitation_logs')
                ->where('notes', 'like', self::MARKER.'%')
                ->count();

            if ($actual !== $expected) {
                // يُبطل المعاملة كلها — أفضل من ترحيل جزئي صامت.
                throw new \RuntimeException("فشل التحقّق من ترحيل student_data: متوقَّع {$expected}، وُجد {$actual}.");
            }
        });

        Log::info('S7: تم ترحيل '.count($rows).' سطرًا من student_data إلى recitation_logs بنجاح.');

        Schema::dropIfExists('student_data');
    }

    public function down()
    {
        // لا مسار تراجع كامل: student_data حُذف، وأي سجلّ أُضيف بعد S7 عبر
        // recitation_logs ليس له مكان في الشكل القديم (سطر واحد لكل طالب).
        // التراجع يعيد الجدول فارغًا فقط — استعادة البيانات الفعلية تكون من
        // نسخة احتياطية سابقة على هذه الهجرة، لا من down() تلقائي.
        Schema::create('student_data', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique();
            $table->unsignedBigInteger('surah_id')->nullable();
            $table->string('legacy_surah_text')->nullable();
            $table->integer('the_ayah')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
            $table->foreign('surah_id')->references('id')->on('surahs')->onDelete('set null');
        });
    }
};
