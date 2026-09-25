<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AstrologerReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'astrologer_id',
        'rating',
        'review',
        'is_active',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Review belongs to user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Review belongs to astrologer.
     */
    public function astrologer(): BelongsTo
    {
        return $this->belongsTo(AiAstrologer::class, 'astrologer_id');
    }
}