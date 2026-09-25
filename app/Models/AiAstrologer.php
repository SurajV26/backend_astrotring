<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class AiAstrologer extends Model
{
    use HasFactory;
    
    protected $table = 'ai_astrologers';

    protected $fillable = [
        'name',
        'slug',
        'image',
        'chat_price',
        'experience',
        'education',
        'about',
        'status'
    ];

    protected $casts = [
        'chat_price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function expertises()
    {
        return $this->hasMany(AiAstrologerExpertise::class, 'ai_astrologer_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AstrologerReview::class, 'astrologer_id');
    }
}