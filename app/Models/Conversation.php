<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'freshchat_id',
        'status',
        'channel',
        'created_at',
        'updated_at',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
