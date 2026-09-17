<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترتيب الحفظ المعكوس (S14).
 *
 * ترتيب المصحف يبدأ بالفاتحة وينتهي بالناس، لكن ترتيب الحفظ الفعلي في الحلقات
 * معكوس: يبدأ الطالب من قصار السور في آخر المصحف (الناس) ويصعد نحو الطوال
 * (البقرة). كانت نسبة التقدّم قبل هذه الهجرة تُحسب بترتيب المصحف، فتُظهر لطالب
 * أتمّ جزء عمّ كاملًا نسبة قريبة من الصفر — رقم صحيح حسابيًا وكاذب تربويًا.
 *
 * عمودان يحملان القرار كاملًا في القاعدة لا في الكود:
 *
 * 1) memorization_order: رتبة السورة في تسلسل الحفظ = 115 − رقم السورة.
 *    الناس (114) ⇒ 1 · الفلق (113) ⇒ 2 · … · البقرة (2) ⇒ 113.
 *
 * 2) excluded_from_progress: الفاتحة وحدها. تبقى قابلة للتسجيل والتسميع
 *    والمراجعة كأي سورة (وهي أكثر ما يُكرَّر فعلًا)، لكنها لا تدخل في المقام
 *    (113 سورة) ولا تضيف شيئًا إلى النسبة.
 *
 * لماذا عمود لا معادلة مكتوبة في الكود: تغيير سلّم القياس لاحقًا (وزن بعدد
 * الآيات مثلًا بدل وزن متساوٍ لكل سورة) يصبح تعديل قيم عمود واحد، بلا لمس أي
 * متحكّم أو شاشة أو اختبار.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('surahs', function (Blueprint $table) {
            $table->unsignedSmallInteger('memorization_order')->nullable()->after('number');
            $table->boolean('excluded_from_progress')->default(false)->after('ayah_count');
        });

        // الفاتحة: مستثناة، وبلا رتبة في تسلسل الحفظ.
        DB::table('surahs')->where('number', 1)->update([
            'memorization_order'     => null,
            'excluded_from_progress' => true,
        ]);

        // بقية السور: الرتبة معكوسة عن ترتيب المصحف.
        DB::table('surahs')->where('number', '>', 1)->update([
            'memorization_order' => DB::raw('115 - number'),
        ]);
    }

    public function down()
    {
        Schema::table('surahs', function (Blueprint $table) {
            $table->dropColumn(['memorization_order', 'excluded_from_progress']);
        });
    }
};
