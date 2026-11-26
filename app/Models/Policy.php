<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    use HasFactory;

    protected $table = 'policies';
    
    protected $fillable = [
        'policy_category_id',
        'name',
        'slug',
        'image',
        'date',
        'description',
    ];

    public function category()
    {
        return $this->belongsTo(PolicyCategory::class, 'policy_category_id');
    }
}
