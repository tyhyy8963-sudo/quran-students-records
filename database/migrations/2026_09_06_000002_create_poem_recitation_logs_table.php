<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السجلّ الزمني لحفظ/مراجعة المتون (S15) — مستقلّ كليًا عن recitation_logs
 * (قرار صريح: لا مفتاح مشترك ولا استعلام مشترك)، بنفس فلسفة السجلّ الزمني
 * القرآني: سطر جديد لكل جلسة، لا تعديل على سطر قائم.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('poem_recitation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('poem_id');
            $table->unsignedSmallInteger('from_bayt')->nullable();
            $table->unsignedSmallInteger('to_bayt');
            $table->string('type', 20)->default('حفظ');
            $table->string('grade', 20)->nullable();
            $table->text('notes')->nullable();
            $table->date('logged_at');
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
            $table->foreign('poem_id')->references('id')->on('poems')->onDelete('cascade');
            $table->index(['student_id', 'logged_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('poem_recitation_logs');
    }
};
