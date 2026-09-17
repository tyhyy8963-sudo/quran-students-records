<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحضور (S9) — سطر واحد لكل طالب لكل يوم مسجَّل، لا سطر لكل يوم تقويمي.
 * يوم بلا سطر يعني "لم يُسجَّل بعد"، لا "غائب" — الفرق مقصود: الشاشة السريعة
 * تعرض الفراغ كحالة ثالثة مستقلة عن الحضور والغياب.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('student_id')->on('students')->onDelete('cascade');

            // سطر واحد لكل طالب في اليوم — تسجيل ثانٍ لنفس اليوم يُحدِّث الأول
            // (updateOrCreate) لا يُنشئ سطرًا موازيًا.
            $table->unique(['student_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
};
