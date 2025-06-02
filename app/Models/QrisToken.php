<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QrisToken extends Model
{
    protected $table = 'qris_tokens';

    use HasFactory;

    protected $fillable = [
        'token',
        'client_key',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    function checkExpiredToken($token)
    {
        return $this->where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
