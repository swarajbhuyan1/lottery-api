<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = ['email','name',
        'mobile', 'code', 'method', 'expires_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
