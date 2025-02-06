<?php

namespace App\Jobs;

use App\Models\Slot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoCancelSlot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $slot;

    public function __construct(Slot $slot)
    {
        $this->slot = $slot;
    }

    public function handle()
    {
        $slot = Slot::lockForUpdate()->find($this->slot->id);

        if (!$slot) {
            Log::error("AutoCancelSlot: Slot not found", ['id' => $this->slot->id]);
            return;
        }

        // Only cancel if still active and not full
        if ($slot->status === 'active' && !$slot->isFull()) {
            // Refund all participants
            foreach ($slot->tickets as $ticket) {
                $ticket->user()->increment('wallet_balance', $slot->amount);
            }

            $slot->update([
                'status' => 'canceled',
                'end_time' => now()
            ]);

           Slot::create([
                'amount' => $slot->amount,
                'member_limit' => $slot->member_limit,
                'winning_percentage' => $slot->winning_percentage,
                'start_time' => now(),
                'end_time' => now()->addDays(1),
                'status' => 'active'
            ]);

            Log::info("Slot {$slot->id} canceled. Refunded {$slot->tickets->count()} users.");
        }
    }
}
