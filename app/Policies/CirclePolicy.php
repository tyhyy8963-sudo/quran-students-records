<?php

namespace App\Policies;

use App\Models\Circle;
use App\Models\User;

/**
 * صلاحيات الحلقة — نفس فلسفة StudentPolicy: خط دفاع ثانٍ صريح فوق
 * TeacherScope، لا اعتماد على مصدر حماية واحد.
 */
class CirclePolicy
{
    public function update(User $user, Circle $circle): bool
    {
        return $circle->teacher_id === $user->id;
    }

    public function delete(User $user, Circle $circle): bool
    {
        return $circle->teacher_id === $user->id;
    }
}
