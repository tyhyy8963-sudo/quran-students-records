<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * إغلاق نظام الحسابات (S13).
 *
 * قبل هذه الهجرة كان أي زائر ينشئ حسابًا بنفسه ببريد إلكتروني، ويستعيد كلمة
 * مروره برابط يصله بالبريد. النظام المطلوب مؤسسي مغلق بالكامل: المدير وحده
 * ينشئ حسابات المعلّمين ويسلّمهم بياناتها يدويًا، ولا بريد إلكتروني في
 * المنظومة إطلاقًا — لا كمعرّف دخول ولا كقناة استعادة.
 *
 * لماذا يُحذف عمود البريد بدل تركه اختياريًا: ما دام لا يُستعمل في الدخول ولا
 * في الإشعارات ولا في الاستعادة، فإبقاؤه يعني حقلًا يطلبه النظام من المدير بلا
 * فائدة واحدة، وبيانات شخصية تُخزَّن بلا سبب. الحذف أصدق من التعطيل الصامت.
 *
 * ملاحظة تقنية على الحذف: SQLite لا يسمح بحذف عمود عليه فهرس، لذا يُسقَط فهرس
 * التفرّد أولًا ثم يُحذف العمود بجملة صريحة (ALTER TABLE ... DROP COLUMN مدعومة
 * في SQLite منذ 3.35 وفي MySQL/MariaDB أصلًا) — بلا حاجة إلى doctrine/dbal
 * الذي يتطلّبه ->change() في Laravel 9.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // اسم المستخدم يبقى nullable على مستوى القاعدة (جعله NOT NULL بعد
            // التعبئة يستلزم doctrine/dbal) — الإلزام يجري في التحقّق من
            // المدخلات وفي فهرس التفرّد أدناه.
            $table->string('username', 50)->nullable()->after('name');
            $table->string('role', 20)->default('teacher')->after('username');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        // تعبئة أسماء المستخدمين للحسابات القائمة من الجزء المحلّي في البريد،
        // مع فضّ التكرار برقم لاحق — حتى لا يفقد معلّم قائم قدرته على الدخول
        // بعد تحويل معرّف الدخول من البريد إلى اسم المستخدم.
        $taken = [];

        foreach (DB::table('users')->select('id', 'email')->get() as $row) {
            $base = Str::slug(Str::before((string) ($row->email ?? ''), '@'), '_') ?: 'user';
            $base = Str::limit($base, 40, '');

            $candidate = $base;
            $suffix = 1;

            while (isset($taken[$candidate])) {
                $candidate = $base.'_'.(++$suffix);
            }

            $taken[$candidate] = true;

            DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        // إسقاط فهرس التفرّد على البريد قبل حذف العمود (شرط SQLite).
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        DB::statement('ALTER TABLE users DROP COLUMN email');
        DB::statement('ALTER TABLE users DROP COLUMN email_verified_at');

        // جدول رموز استعادة كلمة المرور: لا مسار استعادة ذاتيًا بعد اليوم،
        // فالجدول يصبح بيانات ميّتة تحمل رموزًا صالحة نظريًا — يُسقَط.
        Schema::dropIfExists('password_resets');
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
        });

        foreach (['username', 'role', 'is_active', 'last_login_at'] as $column) {
            DB::statement("ALTER TABLE users DROP COLUMN {$column}");
        }

        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
