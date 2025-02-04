<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

        protected $fillable = [
            'user_id',
            'amount',
            'type',
            'status',
            'transaction_id',
            'method',
            'meta'
        ];

        protected $casts = [
            'meta' => 'array' // For storing JSON payment details
        ];

        public function user()
        {
            return $this->belongsTo(User::class);
        }
}
