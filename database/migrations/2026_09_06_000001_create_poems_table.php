<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نموذج المتون (S15) — مرجع ثابت لمتون الحفظ المشهورة، قابل للإضافة لاحقًا من
 * لوحة المدير (Admin\PoemController)، بلا حذف أو تعديل من الواجهة.
 *
 * يحمل الاسم وعدد الأبيات فقط — القياس بالبيت لا بالآية، فلا صلة بجدول surahs.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('poems', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('bayt_count');
            $table->timestamps();
        });

        $poems = [
            ['تحفة الأطفال', 61],
            ['الجزرية', 107],
            ['الشاطبية', 1173],
            ['الدرة المضية', 241],
            ['طيبة النشر', 1014],
        ];

        $now = now();
        DB::table('poems')->insert(array_map(fn ($p) => [
            'name' => $p[0], 'bayt_count' => $p[1], 'created_at' => $now, 'updated_at' => $now,
        ], $poems));
    }

    public function down()
    {
        Schema::dropIfExists('poems');
    }
};
