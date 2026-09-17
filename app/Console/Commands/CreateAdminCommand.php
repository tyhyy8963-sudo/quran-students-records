<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccountPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

/**
 * إنشاء حساب المدير الأول (S13).
 *
 * في منظومة مغلقة لا يوجد "سجّل الآن" لأول مستخدم أيضًا — فأول حساب لا بدّ أن
 * يُنشأ من الخادم نفسه، من يد من يملك وصولًا فعليًا إليه. بعد هذا الحساب تُنشأ
 * بقية الحسابات من داخل النظام.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'keshf:create-admin
                            {--username= : اسم المستخدم للدخول}
                            {--name= : الاسم الظاهر}
                            {--password= : كلمة المرور (تُولَّد تلقائيًا إن تُركت)}';

    protected $description = 'إنشاء حساب مدير للنظام (يُستخدم لأول تنصيب أو لإضافة مدير آخر)';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('اسم المستخدم للدخول');
        $name = $this->option('name') ?: $this->ask('الاسم الظاهر', 'مدير النظام');

        $validator = Validator::make(
            ['username' => $username, 'name' => $name],
            [
                'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users', 'username')],
                'name'     => ['required', 'string', 'max:255'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $plainPassword = $this->option('password') ?: AccountPassword::generate();

        if (mb_strlen($plainPassword) < 6) {
            $this->error('كلمة المرور يجب أن تكون 6 رموز أو أكثر.');

            return self::FAILURE;
        }

        User::create([
            'name'      => $name,
            'username'  => $username,
            'role'      => User::ROLE_ADMIN,
            'is_active' => true,
            'password'  => Hash::make($plainPassword),
        ]);

        $this->newLine();
        $this->info('تم إنشاء حساب المدير:');
        $this->line("  اسم المستخدم : {$username}");
        $this->line("  كلمة المرور  : {$plainPassword}");
        $this->newLine();
        $this->warn('لن تُعرض كلمة المرور مرة أخرى — احفظها الآن.');

        return self::SUCCESS;
    }
}
