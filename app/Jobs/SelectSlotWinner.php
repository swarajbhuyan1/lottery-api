<?php

namespace App\Jobs;

use App\Models\Slot;
use App\Models\Winner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SelectSlotWinner implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $slot;
    protected $processingStage;

    public function __construct(Slot $slot, $processingStage = 'initial')
    {
        $this->slot = $slot;
        $this->processingStage = $processingStage;
    }

    public function handle()
    {
        try {
            $slot = Slot::lockForUpdate()->findOrFail($this->slot->id);

            switch ($this->processingStage) {
                case 'initial':
                    $this->handleInitialStage($slot);
                    break;

                case 'processing':
                    $this->handleProcessingStage($slot);
                    break;

                case 'declaration':
                    $this->handleDeclarationStage($slot);
                    break;
            }

        } catch (\Exception $e) {
            Log::error("Job failed: " . $e->getMessage());
            $slot->update(['status' => 'pending']);
            throw $e;
        }
    }

    private function handleInitialStage(Slot $slot)
    {
        if ($slot->status !== 'pending') {
            Log::error("Initial stage: Invalid slot state", ['status' => $slot->status]);
            return;
        }

        // First transition to processing state
        $slot->update(['status' => 'processing']);

        // Schedule next stage after 30 seconds
        self::dispatch($slot, 'processing')
            ->delay(now()->addSeconds(30))
            ->onQueue('slot_winners');
    }

    private function handleProcessingStage(Slot $slot)
    {
        if ($slot->status !== 'processing') {
            Log::error("Processing stage: Invalid slot state", ['status' => $slot->status]);
            return;
        }

        // Keep processing status for 30 seconds
        // No action needed here, just schedule next stage
        self::dispatch($slot, 'declaration')
            ->delay(now()->addSeconds(30))
            ->onQueue('slot_winners');
    }

    private function handleDeclarationStage(Slot $slot)
    {
        if ($slot->status !== 'processing') {
            Log::error("Declaration stage: Invalid slot state", ['status' => $slot->status]);
            return;
        }

        // Select winner
        $winnerTicket = $slot->tickets()->inRandomOrder()->firstOrFail();
        $winningAmount = $this->calculateWinningAmount($slot);

        // Create winner record
        Winner::create([
            'slot_id' => $slot->id,
            'user_id' => $winnerTicket->user_id,
            'ticket_id' => $winnerTicket->id,
            'winning_amount' => $winningAmount
        ]);

        // Update winner's wallet balance atomically
        $winnerTicket->user->increment('wallet_balance', $winningAmount);

        // Finalize slot
        $slot->update([
            'status' => 'completed',
            'end_time' => now()
        ]);
    }

    private function calculateWinningAmount(Slot $slot)
    {
        return $slot->amount * $slot->member_limit * 0.8;

    }
}
