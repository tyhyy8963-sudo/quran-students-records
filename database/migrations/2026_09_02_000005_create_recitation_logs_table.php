<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السجلّ الزمني (S7).
 *
 * كان لكل طالب سطر واحد فقط في student_data — "موضعه الحالي" فحسب، بلا أي
 * أثر لما قبله. لا يمكن معرفة متى حفظ الطالب سورة البقرة، ولا مقارنة
 * تقدّمه بين شهرين، ولا التمييز بين "حفظ جديد" و"مراجعة" و"تسميع" لنفس
 * المقطع. recitation_logs سجلّ مفتوح (hasMany) بدل سطر واحد يُستبدَل: كل
 * جلسة تضيف سطرًا، ولا شيء يُمحى.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('recitation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('surah_id')->nullable(); // bigint موحّد مع مفتاح surahs
            $table->unsignedSmallInteger('from_ayah')->nullable();
            $table->unsignedSmallInteger('to_ayah');
            $table->string('type', 20)->default('حفظ');
            $table->string('grade', 20)->nullable();
            $table->text('notes')->nullable();
            $table->date('logged_at');
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');
            $table->foreign('surah_id')->references('id')->on('surahs')->onDelete('set null');
            $table->index(['student_id', 'logged_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('recitation_logs');
    }
};
