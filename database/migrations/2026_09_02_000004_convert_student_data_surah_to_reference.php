<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * تحويل "السورة" من نص حرّ إلى مرجع (S6).
 *
 * الخطر المذكور في خطة السبرنتات: نص حرّ مكتوب يدويًا لن يطابق كله اسم
 * السورة القياسي (فروقات همزة، "سورة" بادئة، مسافات). المطابقة هنا تُطبَّع
 * النص (حذف بادئة "سورة"، توحيد الألف والياء والهمزات الشائعة، تقليم
 * المسافات) قبل المقارنة. أي سطر لا يُطابَق يبقى نصّه الأصلي في
 * `legacy_surah_text` بدل ضياعه، ليصحّحه المعلّم يدويًا من نموذج التعديل.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('student_data', function (Blueprint $table) {
            // bigint موحّد مع مفتاح surahs (id() القياسي)، لا tinyint — نوعان
            // مختلفان لعمودي مفتاح أجنبي يرفضهما MySQL بخطأ 1005 عند إنشاء القيد.
            $table->unsignedBigInteger('surah_id')->nullable()->after('student_id');
            $table->string('legacy_surah_text')->nullable()->after('surah_id');
        });

        $surahs = DB::table('surahs')->get(['id', 'name']);
        $normalize = function (?string $text): ?string {
            if ($text === null) {
                return null;
            }
            $text = trim($text);
            $text = preg_replace('/^سورة\s+/u', '', $text);
            $text = str_replace(['أ', 'إ', 'آ', 'ى', 'ة'], ['ا', 'ا', 'ا', 'ي', 'ه'], $text);
            return trim($text);
        };

        $index = [];
        foreach ($surahs as $surah) {
            $index[$normalize($surah->name)] = $surah->id;
        }

        DB::table('student_data')->orderBy('id')->chunkById(100, function ($rows) use ($index, $normalize) {
            foreach ($rows as $row) {
                $key = $normalize($row->the_surah);
                $surahId = $key !== null && $key !== '' ? ($index[$key] ?? null) : null;

                DB::table('student_data')->where('id', $row->id)->update([
                    'surah_id'          => $surahId,
                    'legacy_surah_text' => $surahId === null ? $row->the_surah : null,
                ]);
            }
        });

        Schema::table('student_data', function (Blueprint $table) {
            $table->foreign('surah_id')->references('id')->on('surahs')->onDelete('set null');
            $table->dropColumn('the_surah');
        });
    }

    public function down()
    {
        Schema::table('student_data', function (Blueprint $table) {
            $table->string('the_surah')->nullable();
        });

        DB::table('student_data')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $name = $row->legacy_surah_text;
                if ($name === null && $row->surah_id !== null) {
                    $name = DB::table('surahs')->where('id', $row->surah_id)->value('name');
                }
                DB::table('student_data')->where('id', $row->id)->update(['the_surah' => $name]);
            }
        });

        Schema::table('student_data', function (Blueprint $table) {
            $table->dropForeign(['surah_id']);
            $table->dropColumn(['surah_id', 'legacy_surah_text']);
        });
    }
};
