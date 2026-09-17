<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * مرجع أرباع الأحزاب (S16) — 240 ربعًا ثابتة، أساس وزن نسبة المراجعة في
 * ReviewProgress. لا تُنشأ ولا تُحذف من التطبيق، تمامًا كمرجع السور.
 */
class Quarter extends Model
{
    public $timestamps = true;

    /** عدد أرباع الأحزاب: 60 حزبًا × 4 = 240، مقام نسبة المراجعة. */
    public const COUNT = 240;

    protected $fillable = [
        'quarter_number', 'hizb_number', 'quarter_in_hizb', 'start_surah_id', 'start_ayah', 'start_global_ayah',
    ];

    protected $casts = [
        'quarter_number'    => 'integer',
        'hizb_number'        => 'integer',
        'quarter_in_hizb'    => 'integer',
        'start_ayah'         => 'integer',
        'start_global_ayah'  => 'integer',
    ];

    public function startSurah()
    {
        return $this->belongsTo(Surah::class, 'start_surah_id');
    }
}
