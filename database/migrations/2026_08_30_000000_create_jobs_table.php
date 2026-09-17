<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طابور المهام (B-03).
 *
 * كان إرسال البريد متزامنًا (QUEUE_CONNECTION=sync)، فيبقى الطلب معلّقًا حتى
 * تنتهي محادثة SMTP كاملة، وأي فشل فيها يرفع استثناءً غير معالَج فيعود HTML
 * لخطأ 500 إلى res.json() الذي يتوقّع JSON.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('jobs');
    }
};
