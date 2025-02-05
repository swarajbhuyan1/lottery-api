<?php

namespace App\Jobs;

use App\Models\Slot;
use App\Models\Winner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SelectSlotWinner implements ShouldQueue
{
    use Queueable;
    protected $slot;
    /**
     * Create a new job instance.
     */
    public function __construct(Slot $slot)
    {
//        $this->slot = $slot;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $slot = Slot::find($this->slot->id);

        if (!$slot) {
            Log::error("Slot not found for ID: {$this->slot->id}");
            return;
        }

        // Select random winner
        $winnerTicket = $this->slot->tickets()->inRandomOrder()->first();

        // Create winner record
        Winner::create([
            'slot_id' => $this->slot->id,
            'user_id' => $winnerTicket->user_id,
            'ticket_id' => $winnerTicket->id,
            'winning_amount' => $this->calculateWinningAmount()
        ]);

        // Update slot status
        $this->slot->update(['status' => 'completed']);
    }

    private function calculateWinningAmount()
    {
        // Example: 80% of total slot amount
        $total = $this->slot->amount * $this->slot->member_limit;
        return $total * 0.8;
    }
}
