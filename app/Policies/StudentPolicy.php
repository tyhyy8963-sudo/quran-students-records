<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * صلاحيات الطالب (B-04).
 *
 * تكمّل TeacherScope: النطاق يمنع معلّمًا من أن يرى طالب معلّم آخر أصلًا،
 * وهذه السياسة خط دفاع ثانٍ صريح عند أي استعلام قد يتجاوز النطاق (withoutGlobalScope
 * في أداة إدارية مستقبلية مثلًا) — فلا تعتمد الحماية على مصدر واحد فقط.
 */
class StudentPolicy
{
    public function update(User $user, Student $student): bool
    {
        return $student->teacher_id === $user->id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $student->teacher_id === $user->id;
    }

    public function restore(User $user, Student $student): bool
    {
        return $student->teacher_id === $user->id;
    }
}
