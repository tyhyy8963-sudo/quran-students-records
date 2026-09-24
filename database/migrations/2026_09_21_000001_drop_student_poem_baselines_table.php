<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة أرضية المتن اليدوية (S24) — قرار صريح من يحيى، نفس القرار وبنفس
 * السبب الذي أُلغيت به أرضية حفظ القرآن اليدوية سابقًا (راجع
 * 2026_09_17_000001_drop_quran_baseline_surah_id_from_students_table.php):
 * "المتون تبقى بأرضية يدوية لأنها متوازية التتبّع..." كان قرارًا سابقًا
 * موثَّقًا صراحة (راجع StudentRecordCardTest.php قبل هذا التعديل)، لكن يحيى
 * طلب صراحةً معاملتها الآن مثل القرآن تمامًا — لا إدخال يدوي لـ"ما قبل
 * الانضمام"، الاعتماد كليًا على السجلّات الفعلية (راجع PoemProgress).
 *
 * migration جديدة لا تعديل على 2026_09_07_000002 نفسها — لنفس السبب الموثَّق
 * في migration حذف عمود القرآن: تلك قد تكون نُفِّذت فعليًا على قاعدة بيانات
 * حيّة، فتعديلها لاحقًا لا يُصحِّح شيئًا هناك.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('student_poem_baselines');
    }

    public function down()
    {
        if (Schema::hasTable('student_poem_baselines')) {
            return;
        }

        Schema::create('student_poem_baselines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('poem_id');
            $table->unsignedSmallInteger('baseline_bayt');
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->unique(['student_id', 'poem_id']);
        });
    }
};
