<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إزالة أرضية الحفظ اليدوية (S15، مصحَّح) — قرار صريح من صاحب المنظومة:
 * "المفترض هذا الخيار يكون غير موجود". لا حاجة لهذا العمود أصلًا: نسبة الحفظ
 * تُحتسب بالانسياب التلقائي من أبعد سورة مسجَّلة فعليًا (راجع
 * MemorizationProgress::applyCascade())، بلا أي إدخال يدوي منفصل.
 *
 * migration جديدة لا تعديل على 2026_09_07_000001 نفسها — تلك قد تكون نُفِّذت
 * فعليًا على قاعدة بيانات حيّة، فتعديلها لاحقًا لا يُصحِّح شيئًا هناك. الفحص
 * بـ hasColumn يجعلها آمنة سواء نُفِّذت تلك الهجرة سابقًا أو لم تُنفَّذ بعد.
 *
 * SQLite (بيئة الاختبارات، phpunit.xml: DB_CONNECTION=sqlite) لا يدعم إسقاط
 * مفتاح أجنبي عبر ALTER TABLE إطلاقًا مهما كانت الحالة — Illuminate\Database\
 * Schema\Blueprint يرفض أمر dropForeign على sqlite برسالة صريحة (لا يحتاج
 * إعادة بناء الجدول كليًا لإسقاط عمود واحد لأن القيد هنا معرَّف كجزء من تعريف
 * العمود نفسه لا قيدًا منفصلًا على مستوى الجدول). لذا dropForeign() يُستدعى
 * فقط على محرّكات تدعمه فعليًا (MySQL في بيئة الإنتاج)، بينما dropColumn()
 * وحدها تكفي على sqlite: إسقاط العمود يُسقط قيده المرفق معه تلقائيًا.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('students', 'quran_baseline_surah_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['quran_baseline_surah_id']);
            }

            $table->dropColumn('quran_baseline_surah_id');
        });
    }

    public function down()
    {
        if (Schema::hasColumn('students', 'quran_baseline_surah_id')) {
            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('quran_baseline_surah_id')->nullable()->after('circle_id');
            $table->foreign('quran_baseline_surah_id')->references('id')->on('surahs')->nullOnDelete();
        });
    }
};
