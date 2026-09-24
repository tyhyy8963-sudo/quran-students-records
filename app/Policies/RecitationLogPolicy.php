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

    /**
     * (تصحيح صريح من يحيى): زرّ "✕" للتراجع الفوري استُبدل بأيقونة تعديل
     * حقيقية (✎) تفتح نفس نافذة التسجيل معبّأة لتصحيح سجلّ اليوم مباشرة —
     * نفس فحص الملكية المستعمل أصلًا لـdelete().
     */
    public function update(User $user, RecitationLog $log): bool
    {
        return $this->delete($user, $log);
    }
}
