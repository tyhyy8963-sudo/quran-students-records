<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccountPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * مسار الطوارئ الوحيد لاسترجاع الدخول (S13).
 *
 * بعد إزالة الاستعادة بالبريد كليًا، كلمة مرور المعلّم يعيدها المدير من لوحته.
 * لكن المدير نفسه إن نسي كلمته لا يجد أحدًا فوقه — وبلا هذا الأمر يصبح النظام
 * مقفلًا نهائيًا بلا أي طريق للعودة. لذلك المسار من الخادم مباشرة: من يملك
 * وصولًا إلى الطرفية يملك أصلًا وصولًا إلى قاعدة البيانات، فلا صلاحية جديدة
 * تُمنَح هنا — فقط طريق مريح لما هو ممكن أصلًا لمن بيده الخادم.
 */
class ResetPasswordCommand extends Command
{
    protected $signature = 'keshf:reset-password
                            {username : اسم المستخدم المراد إعادة تعيين كلمته}
                            {--password= : كلمة المرور الجديدة (تُولَّد تلقائيًا إن تُركت)}
                            {--activate : إعادة تفعيل الحساب إن كان معطَّلًا}';

    protected $description = 'إعادة تعيين كلمة مرور أي حساب من الخادم (طوارئ فقدان دخول المدير)';

    public function handle(): int
    {
        $username = $this->argument('username');

        /** @var User|null $user */
        $user = User::where('username', $username)->first();

        if ($user === null) {
            $this->error("لا يوجد حساب باسم المستخدم: {$username}");

            return self::FAILURE;
        }

        $plainPassword = $this->option('password') ?: AccountPassword::generate();

        if (mb_strlen($plainPassword) < 6) {
            $this->error('كلمة المرور يجب أن تكون 6 رموز أو أكثر.');

            return self::FAILURE;
        }

        $attributes = ['password' => Hash::make($plainPassword)];

        if ($this->option('activate')) {
            $attributes['is_active'] = true;
        }

        $user->forceFill($attributes)->save();

        $this->newLine();
        $this->info("تمت إعادة تعيين كلمة مرور: {$user->name} ({$user->roleLabel()})");
        $this->line("  اسم المستخدم : {$user->username}");
        $this->line("  كلمة المرور  : {$plainPassword}");

        if (! $user->is_active) {
            $this->newLine();
            $this->warn('تنبيه: الحساب معطَّل حاليًا ولن يتمكّن من الدخول. أضف --activate لإعادة تفعيله.');
        }

        return self::SUCCESS;
    }
}
