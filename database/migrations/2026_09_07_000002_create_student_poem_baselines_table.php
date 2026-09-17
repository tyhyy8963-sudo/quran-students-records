<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أرضية متن لطالب (S16) — نفس فكرة quran_baseline_surah_id لكل متن على حدة:
 * "آخر بيت أتمّه الطالب قبل الانضمام"، تُستخدم كأرضية في PoemProgress.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('student_poem_baselines', function (Blueprint $table) {
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

    public function down()
    {
        Schema::dropIfExists('student_poem_baselines');
    }
};
