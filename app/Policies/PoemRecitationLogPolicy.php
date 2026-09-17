<?php

namespace App\Policies;

use App\Models\PoemRecitationLog;
use App\Models\User;

/**
 * صلاحية سجلّ تسميع متن — نفس نمط RecitationLogPolicy: الملكية عبر معلّم
 * الطالب صاحب السجلّ.
 */
class PoemRecitationLogPolicy
{
    public function delete(User $user, PoemRecitationLog $log): bool
    {
        return $log->student && $log->student->teacher_id === $user->id;
    }
}
