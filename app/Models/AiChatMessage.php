<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiChatMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_chat_messages';

    protected $fillable = [
        'session_id',
        'question_id',
        'sender',
        'message',
        'is_free',
        'question_credit_used',
        'charged_amount',
        'model',
        'tokens_used',
    ];

    protected $casts = [
        'is_free' => 'boolean',
        'question_credit_used' => 'boolean',
        'charged_amount' => 'decimal:2',
        'tokens_used' => 'integer',
    ];

    public function session()
    {
        return $this->belongsTo(AiChatSession::class, 'session_id');
    }

    public function transaction()
    {
        return $this->hasOne(AiChatTransaction::class, 'message_id');
    }

    public function question()
    {
        return $this->belongsTo(
            AiAstrologerExpertiseQuestion::class,
            'question_id'
        );
    }
}