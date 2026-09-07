<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatSession extends Model
{
    use HasFactory;
    
    protected $table = 'ai_chat_sessions';

    protected $fillable = [
        'user_id',
        'astrologer_id',
        'expertise_id',
        'chat_active_since',
        'chat_last_seen_at',
        'chat_billed_minutes',
        'chat_free_used',
        'paid_messages',
        'total_amount',
        'started_at',
        'last_message_at',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'chat_active_since' => 'datetime',
        'chat_last_seen_at' => 'datetime',
        'chat_free_used' => 'boolean',
        'total_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function astrologer()
    {
        return $this->belongsTo(AiAstrologer::class, 'astrologer_id');
    }

    public function expertise()
    {
        return $this->belongsTo(AiAstrologerExpertise::class, 'expertise_id');
    }

    public function messages()
    {
        return $this->hasMany(AiChatMessage::class, 'session_id');
    }

    public function transactions()
    {
        return $this->hasMany(AiChatTransaction::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}