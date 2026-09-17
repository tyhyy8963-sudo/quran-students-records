<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحلقات (S6).
 *
 * معلّم يتابع أكثر من مجموعة طلاب (حلقة الفجر، حلقة العصر، فصل كذا...)
 * كان بلا وسيلة لتجميعهم غير الاعتماد على ذاكرته. حلقة تخصّ معلّمًا واحدًا
 * فقط (لا مشاركة بين معلّمين)، واسمها فريد ضمن حلقات نفس المعلّم فقط.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['teacher_id', 'name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('circles');
    }
};
