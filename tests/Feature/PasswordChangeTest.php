<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * العطل C-01 — تغيير كلمة المرور كان لا يتحقق من كلمة المرور الحالية إطلاقًا.
 * التحقق كان يجري في صفحة سابقة عبر AJAX ونتيجته لا تُخزَّن، فكان أي طلب POST
 * من جلسة مفتوحة يغيّر كلمة المرور دون معرفة الحالية.
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * حساب مدير لا معلّم: تغيير كلمة المرور صار مسار المدير وحده في S13
     * (المعلّم يراجع المدير)، والعطل C-01 يبقى محروسًا على المسار كما هو.
     */
    private function teacher(string $password = 'old-password'): User
    {
        return User::create([
            'name'      => 'مدير النظام',
            'username'  => 'salem',
            'role'      => User::ROLE_ADMIN,
            'password'  => Hash::make($password),
            'mosque'    => 'جامع النور',
            'classroom' => 'حلقة الفجر',
        ]);
    }

    /** @test */
    public function it_rejects_a_password_change_without_the_current_password(): void
    {
        $user = $this->teacher();

        $response = $this->actingAs($user)->post('/edit-password/update', [
            'new_password'              => 'attacker-password',
            'new_password_confirmation' => 'attacker-password',
        ]);

        $response->assertSessionHasErrors('current_password');

        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password),
            'كلمة المرور تغيّرت رغم عدم إرسال كلمة المرور الحالية — العطل C-01 عاد.'
        );
    }

    /** @test */
    public function it_rejects_a_password_change_with_a_wrong_current_password(): void
    {
        $user = $this->teacher();

        $response = $this->actingAs($user)->post('/edit-password/update', [
            'current_password'          => 'not-the-password',
            'new_password'              => 'attacker-password',
            'new_password_confirmation' => 'attacker-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    /** @test */
    public function it_changes_the_password_when_the_current_password_is_correct(): void
    {
        $user = $this->teacher();

        $response = $this->actingAs($user)->post('/edit-password/update', [
            'current_password'          => 'old-password',
            'new_password'              => 'brand-new-password',
            'new_password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    /** @test */
    public function it_rejects_a_new_password_identical_to_the_current_one(): void
    {
        $user = $this->teacher();

        $this->actingAs($user)->post('/edit-password/update', [
            'current_password'          => 'old-password',
            'new_password'              => 'old-password',
            'new_password_confirmation' => 'old-password',
        ])->assertSessionHasErrors('new_password');
    }

    /** @test */
    public function the_removed_ajax_verification_endpoint_no_longer_exists(): void
    {
        // نقطة النهاية القديمة verify-current-password كانت هي ما سمح بفصل
        // التحقق عن الحفظ. حُذفت مع دمج الصفحتين.
        $user = $this->teacher();

        $this->actingAs($user)
            ->post('/verify-current-password', ['password' => 'old-password'])
            ->assertNotFound();
    }
}
