<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyCategory extends Model
{
    use HasFactory;

    protected $table = 'policy_categories';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function policies()
    {
        return $this->hasMany(Policy::class, 'policy_category_id');
    }
}
