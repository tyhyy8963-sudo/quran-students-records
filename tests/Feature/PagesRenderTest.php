<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** كل صفحة تُصيَّر دون خطأ Blade، والمسارات المحمية تحمي فعلًا. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        return User::create([
            'name' => 'الأستاذ نايف', 'username' => 'naif',
            'password' => Hash::make('secret123'),
            'mosque' => 'جامع القدس', 'classroom' => 'حلقة الفجر',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'مدير النظام', 'username' => 'admin',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('secret123'),
        ]);
    }

    /** @test */
    public function guest_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('كشف المتابعة', false);
        $this->get('/login')->assertOk()->assertSee('تسجيل الدخول', false);
    }

    /** @test */
    public function teacher_pages_render(): void
    {
        $user = $this->teacher();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('الأستاذ', false);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('الملف الشخصي', false);
        $this->actingAs($user)->get('/profile/edit')->assertOk();
    }

    /** @test */
    public function admin_pages_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/teachers')->assertOk()->assertSee('حسابات المعلّمين', false);
        $this->actingAs($admin)->get('/admin/teachers/create')->assertOk()->assertSee('اسم المستخدم للدخول', false);
        $this->actingAs($admin)->get('/edit-password')->assertOk()->assertSee('كلمة المرور الحالية', false);
        $this->actingAs($admin)->get('/profile')->assertOk();
    }

    /**
     * @test
     *
     * كل مسار من نظام الحسابات المفتوح أُزيل فعليًا لا أُخفي من الواجهة فقط
     * (S13): إخفاء الرابط وحده يترك المسار مفتوحًا لمن يعرف عنوانه.
     */
    public function self_service_account_routes_no_longer_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', [])->assertNotFound();
        $this->get('/reset-password/any-token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
        $this->get('/edit-password/new')->assertNotFound();
    }

    /** @test */
    public function protected_pages_reject_guests(): void
    {
        foreach (['/dashboard', '/profile', '/profile/edit', '/edit-password', '/admin/teachers'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }
}
