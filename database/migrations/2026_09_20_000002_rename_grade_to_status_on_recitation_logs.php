<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (S22) استبدال حقل "grade" (تقييم جودة: ممتاز/جيد جدًا/جيد/مقبول) بحقل
 * "status" (حالة حفظ: حافظ/غير حافظ) — بطلب صريح من يحيى. إضافة عمود جديد +
 * ترحيل + حذف العمود القديم بدل rename مباشر، حتى تُكتب قيمة الترحيل صراحةً
 * لا انتقالًا صامتًا لنفس القيم تحت اسم مختلف (القيم نفسها تغيّرت معنى
 * ودلالة، لا الاسم فقط).
 *
 * الترحيل: أي سجلّ له grade قديم (أيًا كانت قيمته) ⇐ status = 'حافظ'. وجود
 * تقييم قديم أصلًا يعني أن الطالب سمَّع شيئًا فعليًا وقُيِّم عليه، وهذا أقرب
 * معنى لـ"حافظ" من أي من دلالات التقييم الرباعي القديم — لا يوجد فرق بديل
 * محتمل بين درجات التقييم القديمة يستحقّ تفريقًا هنا بحسب طلب يحيى.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('recitation_logs', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('grade');
        });

        DB::table('recitation_logs')->whereNotNull('grade')->update(['status' => 'حافظ']);

        Schema::table('recitation_logs', function (Blueprint $table) {
            $table->dropColumn('grade');
        });
    }

    public function down()
    {
        Schema::table('recitation_logs', function (Blueprint $table) {
            $table->string('grade', 20)->nullable()->after('status');
        });

        // لا يمكن استرجاع درجة التقييم الرباعي الأصلية من "حافظ/غير حافظ" —
        // راجع تعليق الصنف أعلاه. القيمة الوحيدة الأمينة هنا: ترك grade فارغة.

        Schema::table('recitation_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
