<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    protected $fillable = [
        'amount', 'member_limit', 'status', 'end_time','winning_percentage'
    ];
    protected $casts = [
            'start_time' => 'datetime',
            'end_time' => 'datetime'
        ];

        public function tickets() {
            return $this->hasMany(Ticket::class);
        }

        public function isFull() {
            return $this->tickets()->count() >= $this->member_limit;
        }

        public function winners()
        {
            return $this->hasMany(Winner::class);
        }
    public function declareWinner()
    {
        if (!$this->isFull()) return;

        $winningTicket = $this->tickets()->inRandomOrder()->first();

        Winner::create([
            'user_id' => $winningTicket->user_id,
            'slot_id' => $this->id,
            'ticket_id' => $winningTicket->id,
            'winning_amount' => $this->winning_amount // Set by admin
        ]);

        // Credit winner's wallet
        $winningTicket->user->wallet_balance += $this->winning_amount;
        $winningTicket->user->save();

        // Mark slot as completed
        $this->update(['status' => 'completed']);
    }

}
