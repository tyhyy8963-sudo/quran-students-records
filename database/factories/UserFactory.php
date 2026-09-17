<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * الحساب الافتراضي معلّم نشط باسم مستخدم فريد — لا بريد إلكتروني ولا
 * email_verified_at بعد إغلاق نظام الحسابات (S13).
 */
class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'username' => 'user_'.Str::lower(Str::random(8)),
            'role' => User::ROLE_TEACHER,
            'is_active' => true,
            'mosque' => null,
            'classroom' => null,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /** حساب مدير. */
    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    /** حساب معطَّل — لا يستطيع الدخول ولا متابعة جلسة مفتوحة. */
    public function inactive()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
