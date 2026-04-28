<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'freshchat_id',
        'conversation_id',
        'contact_id',
        'actor_type',
        'content',
        'message_type',
        'is_ai',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_ai' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
