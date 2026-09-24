<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مراجعة عابرة لعدّة سور (S16) — سجلّ "مراجعة" واحد قد يمتدّ من آية في سورة
 * إلى آية في سورة لاحقة (الطالب يراجع فعليًا حزبًا أو نصف حزب، لا يقف
 * بالضرورة عند حدود سورة)، بدل إجبار المعلّم على سطر منفصل لكل سورة يمرّ بها.
 *
 * surah_id يبقى "من سورة" كما كان تمامًا (توافق تامّ مع كل سجلّ قائم وكل
 * شاشة تقرأه). to_surah_id الجديد اختياري ونهايته: NULL يعني ما كان يعنيه
 * دائمًا — سجلّ محصور بسورة واحدة، وهذا ينطبق تلقائيًا على كل صفّ قديم بلا
 * أي هجرة بيانات. لا يُملأ إلا حين يختار المعلّم صراحةً سورة نهاية مختلفة عن
 * سورة البداية، ولا يُسمح بذلك إلا لنوع "مراجعة" (راجع
 * StoreRecitationLogRequest::withValidator()).
 *
 * نفس نمط 2026_09_07_000001 (عمود مفتاح أجنبي اختياري يُضاف لجدول قائم،
 * nullOnDelete لأن السور لا تُحذف من الواجهة فعليًا) — مُختبَر فعليًا وعامل
 * على SQLite (بيئة الاختبارات) بلا أي عطل.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('recitation_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('to_surah_id')->nullable()->after('surah_id');
            $table->foreign('to_surah_id')->references('id')->on('surahs')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('recitation_logs', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['to_surah_id']);
            }

            $table->dropColumn('to_surah_id');
        });
    }
};
