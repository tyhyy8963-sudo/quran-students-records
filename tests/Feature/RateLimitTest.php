<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * العطل C-05 — لم يكن هناك أي throttle على أي مسار: تخمين كلمات المرور بلا سقف.
 *
 * اختبارا محدِّد 'register' أُزيلا مع المسار نفسه في S13 (لا تسجيل ذاتي في
 * منظومة مغلقة)، وحلّ محلّهما اختبار أن المحدِّد يفصل بين أسماء المستخدمين:
 * بعد تحويل معرّف الدخول من البريد إلى username كان المفتاح سيبقى معلَّقًا على
 * حقل لم يعد يُرسَل، فيتشارك كل من خلف نفس الشبكة عدّادًا واحدًا.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_attempts_are_capped_at_five_per_minute(): void
    {
        User::create([
            'name'      => 'الأستاذ سعد',
            'username'  => 'saad',
            'password'  => Hash::make('correct-password'),
            'mosque'    => 'جامع البركة',
            'classroom' => 'حلقة الضحى',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', [
                'username' => 'saad',
                'password' => 'wrong-password',
            ])->assertStatus(302);
        }

        $this->post('/login', [
            'username' => 'saad',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertGuest();
    }

    /** @test */
    public function the_login_limiter_counts_per_username_not_per_ip_alone(): void
    {
        User::create([
            'name' => 'الأستاذ بدر', 'username' => 'badr', 'password' => Hash::make('secret123'),
        ]);
        User::create([
            'name' => 'الأستاذ فهد', 'username' => 'fahad', 'password' => Hash::make('secret123'),
        ]);

        // خمس محاولات خاطئة على "badr" تستهلك حصّته.
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/login', ['username' => 'badr', 'password' => 'wrong'])->assertStatus(302);
        }
        $this->post('/login', ['username' => 'badr', 'password' => 'wrong'])->assertStatus(429);

        // ومع ذلك يبقى دخول "fahad" من نفس الجهاز ممكنًا — عدّاد مستقلّ لكل
        // اسم مستخدم، فمعلّمان على شبكة المسجد نفسها لا يحجب أحدهما الآخر.
        $this->post('/login', ['username' => 'fahad', 'password' => 'secret123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
