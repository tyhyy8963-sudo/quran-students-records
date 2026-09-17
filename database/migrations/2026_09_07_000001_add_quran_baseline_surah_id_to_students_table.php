<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أرضية الحفظ (S16) — "آخر سورة أتمّها الطالب قبل الانضمام"، تُستخدم كأرضية في
 * حساب نسبة التقدّم (MemorizationProgress) بدل تلفيق سجلّات تاريخية لكل طالب
 * قديم. nullOnDelete لأن السور لا تُحذف من الواجهة فعليًا، لكن سلامة القيد لا
 * تعتمد على ذلك.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('quran_baseline_surah_id')->nullable()->after('circle_id');
            $table->foreign('quran_baseline_surah_id')->references('id')->on('surahs')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['quran_baseline_surah_id']);
            $table->dropColumn('quran_baseline_surah_id');
        });
    }
};
