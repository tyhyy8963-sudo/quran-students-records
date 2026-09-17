<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * متن حفظ ثابت (S15) — مرجع يُضاف إليه من لوحة المدير فقط (Admin\PoemController)،
 * لا حذف ولا تعديل من الواجهة.
 */
class Poem extends Model
{
    protected $fillable = [
        'name', 'bayt_count',
    ];

    protected $casts = [
        'bayt_count' => 'integer',
    ];

    public function poemRecitationLogs()
    {
        return $this->hasMany(PoemRecitationLog::class);
    }
}
