<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * (S22) نظير 2026_09_20_000002 بالضبط لكن لجدول poem_recitation_logs —
 * PoemRecitationLog يعيد استخدام RecitationLog::STATUSES (كان GRADES) فيلزم
 * نفس تعديل العمود هنا حتى يتوافق الجدولان.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('poem_recitation_logs', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('grade');
        });

        DB::table('poem_recitation_logs')->whereNotNull('grade')->update(['status' => 'حافظ']);

        Schema::table('poem_recitation_logs', function (Blueprint $table) {
            $table->dropColumn('grade');
        });
    }

    public function down()
    {
        Schema::table('poem_recitation_logs', function (Blueprint $table) {
            $table->string('grade', 20)->nullable()->after('status');
        });

        Schema::table('poem_recitation_logs', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
