<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAstrologyChart  extends Model
{
    use HasFactory;
    
    protected $table = 'user_astrology_charts';

    protected $fillable = [
        'user_id',
        'lagna',
        'moon_sign',
        'sun_sign',
        'nakshatra',
        'pada',
        'planets',
        'charts',
        'dasha',
        'transit',
        'raw_data',
    ];

    protected $casts = [
        'planets' => 'array',
        'charts' => 'array',
        'dasha' => 'array',
        'transit' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}