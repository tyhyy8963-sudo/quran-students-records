<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->unsignedBigInteger('circle_id')->nullable()->after('teacher_id');

            // حذف الحلقة لا يحذف طلابها — تعود الحلقة إلى "بلا حلقة" فقط.
            $table->foreign('circle_id')->references('id')->on('circles')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['circle_id']);
            $table->dropColumn('circle_id');
        });
    }
};
