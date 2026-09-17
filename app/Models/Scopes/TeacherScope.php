<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * عزل بيانات المعلّمين (العطل B-04).
 *
 * كان `where('teacher_id', auth()->id())` مكتوبًا يدويًا في كل ميثود من
 * StudentController — صحيح اليوم، لكنه حماية بالانضباط لا بالبنية: أول
 * ميثود جديد يُنسى فيه هذا السطر يسرّب طلاب معلّم إلى معلّم آخر بلا أي إنذار.
 *
 * هذا النطاق يُطبَّق تلقائيًا على كل استعلام لموديل Student طالما هناك
 * مستخدم مسجّل دخوله — فالحماية جزء من تعريف الموديل، لا تفصيل يتكرّر
 * كتابته في كل متحكّم.
 */
class TeacherScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (Auth::check()) {
            $builder->where($model->qualifyColumn('teacher_id'), Auth::id());
        }
    }
}
