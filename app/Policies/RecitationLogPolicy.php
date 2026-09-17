<?php

namespace App\Policies;

use App\Models\RecitationLog;
use App\Models\User;

/**
 * صلاحية سجلّ التسميع — الملكية تُفحص عبر معلّم الطالب صاحب السجلّ، فلا
 * حاجة لعمود teacher_id مكرَّر على recitation_logs نفسها.
 */
class RecitationLogPolicy
{
    public function delete(User $user, RecitationLog $log): bool
    {
        return $log->student && $log->student->teacher_id === $user->id;
    }
}
