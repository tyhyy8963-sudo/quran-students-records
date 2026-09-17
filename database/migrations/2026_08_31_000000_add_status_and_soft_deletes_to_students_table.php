<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة الطالب والحذف الناعم (B-05).
 *
 * كان الحذف نهائيًا مع cascade يمحو student_data معه — ضغطة واحدة بالخطأ
 * تمحو سجلّ طالب كاملًا بلا استرجاع، والطالب المنتقل إلى حلقة أخرى ليس
 * طالبًا محذوفًا. الآن: `status` يميّز الحالات الحقيقية، و`deleted_at`
 * يجعل كل حذف قابلًا للتراجع.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('teacher_id');
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['status', 'deleted_at']);
        });
    }
};
