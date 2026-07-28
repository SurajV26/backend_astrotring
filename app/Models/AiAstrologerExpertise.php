<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAstrologerExpertise extends Model
{
    use HasFactory;
    
    protected $table = 'ai_astrologer_expertises';

    protected $fillable = [
        'ai_astrologer_id',
        'name',
        'slug',
        'relevant_chart',
        'status'
    ];

    protected $casts = [
        'relevant_chart' => 'array',
        'status' => 'boolean',
    ];

    public function astrologer()
    {
        return $this->belongsTo(AiAstrologer::class, 'ai_astrologer_id');
    }

    public function questions()
    {
        return $this->hasMany(AiAstrologerExpertiseQuestion::class, 'expertise_id');
    }
}