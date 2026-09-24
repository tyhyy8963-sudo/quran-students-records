<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * نظام الحسابات المغلق (S13).
 *
 * القاعدة التي تحرسها هذه الاختبارات: لا أحد ينشئ حسابًا لنفسه، ولا أحد يغيّر
 * كلمة مروره إلا المدير، ولا يبقى النظام بلا مدير — لا بالحذف ولا بالتعطيل
 * ولا بتغيير الدور.
 */
class ClosedAccountSystemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $password = 'admin-pass'): User
    {
        return User::create([
            'name' => 'مدير النظام', 'username' => 'admin',
            'role' => User::ROLE_ADMIN, 'password' => Hash::make($password),
        ]);
    }

    private function teacher(string $username = 'teacher1', string $password = 'teacher-pass'): User
    {
        return User::create([
            'name' => 'الأستاذ سعد', 'username' => $username,
            'password' => Hash::make($password), 'mosque' => 'جامع البركة', 'classroom' => 'حلقة الفجر',
        ]);
    }

    /* ===================== الدخول ===================== */

    /** @test */
    public function a_teacher_logs_in_with_a_username_and_lands_on_the_students_board(): void
    {
        $this->teacher();

        $this->post('/login', ['username' => 'teacher1', 'password' => 'teacher-pass'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    /** @test */
    public function an_admin_lands_on_the_accounts_panel_not_the_students_board(): void
    {
        $this->admin();

        $this->post('/login', ['username' => 'admin', 'password' => 'admin-pass'])
            ->assertRedirect(route('admin.teachers.index'));
    }

    /** @test */
    public function login_records_the_last_login_time(): void
    {
        $teacher = $this->teacher();
        $this->assertNull($teacher->last_login_at);

        $this->post('/login', ['username' => 'teacher1', 'password' => 'teacher-pass']);

        $this->assertNotNull($teacher->fresh()->last_login_at);
    }

    /** @test */
    public function a_deactivated_account_cannot_log_in_and_is_told_why(): void
    {
        $teacher = $this->teacher();
        $teacher->forceFill(['is_active' => false])->save();

        $this->post('/login', ['username' => 'teacher1', 'password' => 'teacher-pass'])
            ->assertSessionHas('error');

        // ليست "كلمة مرور خاطئة": المعلّم يجب أن يعرف أن حسابه معطَّل لا أن
        // يعيد المحاولة عشر مرّات بكلمة صحيحة.
        $this->assertStringContainsString('معطَّل', session('error'));
        $this->assertGuest();
    }

    /** @test */
    public function deactivating_a_teacher_ends_an_already_open_session(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/dashboard')->assertOk();

        $teacher->forceFill(['is_active' => false])->save();

        $this->actingAs($teacher)->get('/dashboard')->assertRedirect(route('login'));
    }

    /* ===================== حدود دور المعلّم ===================== */

    /** @test */
    public function a_teacher_cannot_reach_the_admin_panel(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/admin/teachers')->assertForbidden();
        $this->actingAs($teacher)->get('/admin/teachers/create')->assertForbidden();
        $this->actingAs($teacher)->post('/admin/teachers', [])->assertForbidden();
    }

    /** @test */
    public function a_teacher_cannot_change_their_own_password_by_any_route(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($teacher)->get('/edit-password')->assertForbidden();

        $this->actingAs($teacher)->post('/edit-password/update', [
            'current_password'          => 'teacher-pass',
            'new_password'              => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('teacher-pass', $teacher->fresh()->password));
    }

    /** @test */
    public function an_admin_visiting_a_teacher_screen_is_sent_to_the_accounts_panel(): void
    {
        // ليس منعًا أمنيًا: شاشات المعلّم معزولة بـTeacherScope فتظهر للمدير
        // فارغة دائمًا، وشاشة فارغة تُقرأ كأن البيانات ضاعت.
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertRedirect(route('admin.teachers.index'));
    }

    /* ===================== إنشاء الحسابات ===================== */

    /** @test */
    public function an_admin_creates_a_teacher_account_and_sees_its_password_once(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/teachers', [
            'name'      => 'الأستاذ ماجد',
            'username'  => 'majed',
            'mosque'    => 'جامع النور',
            'classroom' => 'حلقة العصر',
            'password'  => 'chosen-pass',
        ]);

        $response->assertRedirect(route('admin.teachers.index'))
            ->assertSessionHas('issued_credentials');

        $credentials = session('issued_credentials');
        $this->assertSame('majed', $credentials['username']);
        $this->assertSame('chosen-pass', $credentials['password']);

        $this->assertDatabaseHas('users', [
            'username' => 'majed', 'role' => User::ROLE_TEACHER, 'is_active' => true,
        ]);

        // الحساب المُنشأ يعمل فعلًا بالكلمة المعروضة — لا "بيانات تُسلَّم" ثم
        // يكتشف المعلّم أنها لا تدخل.
        $this->post('/login', ['username' => 'majed', 'password' => 'chosen-pass'])
            ->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function a_password_is_generated_when_the_admin_leaves_it_blank(): void
    {
        $this->actingAs($this->admin())->post('/admin/teachers', [
            'name' => 'الأستاذ فهد', 'username' => 'fahad',
        ])->assertSessionHas('issued_credentials');

        $generated = session('issued_credentials')['password'];

        $this->assertGreaterThanOrEqual(10, strlen($generated));

        // الأبجدية خالية من الرموز المتشابهة عند الإملاء الشفهي.
        $this->assertSame(0, preg_match('/[0O1lI]/', $generated), "كلمة المرور المولَّدة تحوي رمزًا ملتبسًا: {$generated}");

        $this->post('/login', ['username' => 'fahad', 'password' => $generated])
            ->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function usernames_are_unique_and_restricted_to_login_safe_characters(): void
    {
        $this->teacher('taken');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/teachers', ['name' => 'أ', 'username' => 'taken'])
            ->assertSessionHasErrors('username');

        $this->actingAs($admin)
            ->post('/admin/teachers', ['name' => 'أ', 'username' => 'اسم عربي'])
            ->assertSessionHasErrors('username');

        $this->actingAs($admin)
            ->post('/admin/teachers', ['name' => 'أ', 'username' => 'ab'])
            ->assertSessionHasErrors('username');
    }

    /* ===================== إدارة الحسابات ===================== */

    /** @test */
    public function an_admin_resets_a_teacher_password_and_the_old_one_stops_working(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->patch("/admin/teachers/{$teacher->id}/password")
            ->assertSessionHas('issued_credentials');

        $newPassword = session('issued_credentials')['password'];

        $this->assertFalse(Hash::check('teacher-pass', $teacher->fresh()->password));
        $this->assertTrue(Hash::check($newPassword, $teacher->fresh()->password));
    }

    /** @test */
    public function an_admin_can_set_a_chosen_password_instead_of_a_random_one_on_reset(): void
    {
        // الواجهة كانت تعرض "إعادة تعيين" بلا حقل إدخال فتسقط دائمًا على
        // التوليد التلقائي، رغم أن الخادم يقبل password منذ البداية (طلب
        // صاحب المنظومة تخصيص كلمة المرور بنفسه لا انتظار توليد عشوائي دائمًا).
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->patch("/admin/teachers/{$teacher->id}/password", ['password' => 'my-chosen-pass'])
            ->assertSessionHas('issued_credentials');

        $this->assertSame('my-chosen-pass', session('issued_credentials')['password']);
        $this->assertFalse(Hash::check('teacher-pass', $teacher->fresh()->password));
        $this->assertTrue(Hash::check('my-chosen-pass', $teacher->fresh()->password));

        $this->post('/login', ['username' => $teacher->username, 'password' => 'my-chosen-pass'])
            ->assertRedirect(route('dashboard'));
    }

    /** @test */
    public function a_chosen_reset_password_shorter_than_six_characters_is_rejected(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->patch("/admin/teachers/{$teacher->id}/password", ['password' => 'abc'])
            ->assertSessionHasErrors('password');

        // كلمة المرور القديمة تبقى سارية — الطلب المرفوض لم يغيّر شيئًا.
        $this->assertTrue(Hash::check('teacher-pass', $teacher->fresh()->password));
    }

    /**
     * تعديل اسم المستخدم من لوحة الأدمن (طلب صريح من يحيى: كان التعديل
     * المتاح لكل معلّم يقتصر على كلمة المرور فقط، بلا أي طريقة لتغيير اسم
     * المستخدم بعد إنشاء الحساب).
     */
    /** @test */
    public function an_admin_updates_a_teachers_username_and_they_log_in_with_the_new_one(): void
    {
        $teacher = $this->teacher('old_name');

        $this->actingAs($this->admin())
            ->patch("/admin/teachers/{$teacher->id}/username", ['username' => 'new_name'])
            ->assertSessionHas('success');

        $this->assertSame('new_name', $teacher->fresh()->username);

        $this->post('/login', ['username' => 'new_name', 'password' => 'teacher-pass'])
            ->assertRedirect(route('dashboard'));
        $this->post('/logout');

        // الاسم القديم لم يعد يعمل — نفس رسالة "بيانات خاطئة" العامة، لا خطأ تحقّق.
        $this->post('/login', ['username' => 'old_name', 'password' => 'teacher-pass'])
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    /** @test */
    public function a_teachers_username_cannot_be_changed_to_one_already_taken_or_an_invalid_shape(): void
    {
        $this->teacher('taken_name');
        $teacher = $this->teacher('mine_name');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/teachers/{$teacher->id}/username", ['username' => 'taken_name'])
            ->assertSessionHasErrors('username');

        $this->actingAs($admin)
            ->patch("/admin/teachers/{$teacher->id}/username", ['username' => 'اسم عربي'])
            ->assertSessionHasErrors('username');

        $this->actingAs($admin)
            ->patch("/admin/teachers/{$teacher->id}/username", ['username' => 'ab'])
            ->assertSessionHasErrors('username');

        // كل المحاولات المرفوضة أعلاه لم تغيّر شيئًا.
        $this->assertSame('mine_name', $teacher->fresh()->username);
    }

    /** @test */
    public function an_admin_can_deactivate_and_reactivate_a_teacher(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/admin/teachers/{$teacher->id}/active");
        $this->assertFalse($teacher->fresh()->is_active);

        $this->actingAs($admin)->patch("/admin/teachers/{$teacher->id}/active");
        $this->assertTrue($teacher->fresh()->is_active);
    }

    /** @test */
    public function a_teacher_with_students_cannot_be_deleted(): void
    {
        $teacher = $this->teacher();
        Student::create(['student_name' => 'يوسف', 'teacher_id' => $teacher->id]);

        $this->actingAs($this->admin())
            ->delete("/admin/teachers/{$teacher->id}")
            ->assertSessionHasErrors('delete');

        // الحذف بـcascade كان سيمحو الطالب وسجلّه وحضوره كلها بلا إنذار.
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('students', ['teacher_id' => $teacher->id]);
    }

    /** @test */
    public function a_teacher_without_students_can_be_deleted(): void
    {
        $teacher = $this->teacher();

        $this->actingAs($this->admin())
            ->delete("/admin/teachers/{$teacher->id}")
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    }

    /** @test */
    public function the_accounts_screen_refuses_to_touch_another_admin(): void
    {
        $admin = $this->admin();
        $otherAdmin = User::create([
            'name' => 'مدير ثانٍ', 'username' => 'admin2',
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('x123456'),
        ]);

        $this->actingAs($admin)->patch("/admin/teachers/{$otherAdmin->id}/password")->assertForbidden();
        $this->actingAs($admin)->patch("/admin/teachers/{$otherAdmin->id}/username", ['username' => 'x'])->assertForbidden();
        $this->actingAs($admin)->patch("/admin/teachers/{$otherAdmin->id}/active")->assertForbidden();
        $this->actingAs($admin)->delete("/admin/teachers/{$otherAdmin->id}")->assertForbidden();
    }

    /* ===================== حارس آخر مدير ===================== */

    /** @test */
    public function the_last_admin_cannot_be_deleted_or_demoted_or_deactivated(): void
    {
        $admin = $this->admin();

        $this->expectException(\RuntimeException::class);
        $admin->delete();
    }

    /** @test */
    public function the_last_admin_cannot_be_demoted_to_teacher(): void
    {
        $admin = $this->admin();

        $this->expectException(\RuntimeException::class);
        $admin->forceFill(['role' => User::ROLE_TEACHER])->save();
    }

    /** @test */
    public function the_last_admin_cannot_be_deactivated(): void
    {
        $admin = $this->admin();

        $this->expectException(\RuntimeException::class);
        $admin->forceFill(['is_active' => false])->save();
    }

    /** @test */
    public function an_admin_can_be_removed_while_another_active_admin_remains(): void
    {
        $first = $this->admin();
        $second = User::create([
            'name' => 'مدير ثانٍ', 'username' => 'admin2',
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('x123456'),
        ]);

        // المرونة المقصودة: استبدال بيانات اعتماد المدير ممكن ما دام لا يبقى
        // النظام بلا مدير — بخلاف تجميد صفّ بعينه.
        $first->delete();

        $this->assertDatabaseMissing('users', ['id' => $first->id]);
        $this->assertDatabaseHas('users', ['id' => $second->id]);
    }

    /* ===================== أوامر الخادم ===================== */

    /** @test */
    public function the_server_command_creates_an_admin_account(): void
    {
        $this->artisan('keshf:create-admin', [
            '--username' => 'root_admin',
            '--name'     => 'المدير',
            '--password' => 'server-pass',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['username' => 'root_admin', 'role' => User::ROLE_ADMIN]);

        $this->post('/login', ['username' => 'root_admin', 'password' => 'server-pass'])
            ->assertRedirect(route('admin.teachers.index'));
    }

    /** @test */
    public function the_server_command_restores_a_locked_out_admin(): void
    {
        // السيناريو الذي يوجد الأمر لأجله: المدير نسي كلمته ولا أحد فوقه
        // يعيدها، وقد أُزيلت الاستعادة بالبريد كليًا.
        $admin = $this->admin();
        $second = User::create([
            'name' => 'مدير ثانٍ', 'username' => 'admin2',
            'role' => User::ROLE_ADMIN, 'password' => Hash::make('x123456'),
        ]);
        $admin->forceFill(['is_active' => false])->save();

        $this->artisan('keshf:reset-password', [
            'username'   => 'admin',
            '--password' => 'recovered-pass',
            '--activate' => true,
        ])->assertSuccessful();

        $this->post('/login', ['username' => 'admin', 'password' => 'recovered-pass'])
            ->assertRedirect(route('admin.teachers.index'));
    }

    /** @test */
    public function the_server_command_fails_loudly_for_an_unknown_username(): void
    {
        $this->artisan('keshf:reset-password', ['username' => 'ghost'])->assertFailed();
    }

    /* ===================== أثر الإغلاق على البيانات ===================== */

    /** @test */
    public function the_users_table_no_longer_stores_email_addresses(): void
    {
        // الإغلاق ليس إخفاء حقل من الواجهة: العمود نفسه غادر القاعدة، ومعه
        // جدول رموز الاستعادة الذي كان يحمل رموزًا صالحة لحسابات حقيقية.
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertFalse(Schema::hasTable('password_resets'));

        $this->assertTrue(Schema::hasColumn('users', 'username'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
    }
}
