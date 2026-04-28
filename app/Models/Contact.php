<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'freshchat_id',
        'name',
        'email',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
