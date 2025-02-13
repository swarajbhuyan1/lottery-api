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

//    private function handleDeclarationStage(Slot $slot)
//    {
//        if ($slot->status !== 'processing') {
//            Log::error("Declaration stage: Invalid slot state", ['status' => $slot->status]);
//            return;
//        }
//
//        // Select winner
//        $winnerTicket = $slot->tickets()->inRandomOrder()->firstOrFail();
//        $winningAmount = $this->calculateWinningAmount($slot);
//
//        // Create winner record
//        Winner::create([
//            'slot_id' => $slot->id,
//            'user_id' => $winnerTicket->user_id,
//            'ticket_id' => $winnerTicket->id,
//            'is_winner' => 1,
//            'winning_amount' => $winningAmount
//        ]);
//
//        // Update winner's wallet balance atomically
//        $winnerTicket->user->increment('wallet_balance', $winningAmount);
//
//        // Finalize slot
//        $slot->update([
//            'status' => 'completed',
//            'end_time' => now()
//        ]);
//    }
    private function handleDeclarationStage(Slot $slot)
    {
        if ($slot->status !== 'processing') {
            Log::error("Declaration stage: Invalid slot state", ['status' => $slot->status]);
            return;
        }

        $participants = $slot->tickets()->get();
        $totalAmount = $slot->amount * $slot->member_limit;

        if ($slot->category_id == 1) {
            $this->distributeCategoryOnePrizes($slot, $participants, $totalAmount);
        } elseif ($slot->category_id == 2) {
            $this->distributeCategoryTwoPrizes($slot, $participants, $totalAmount);
        }

        $slot->update(['status' => 'completed', 'end_time' => now()]);
    }
//    private function distributeCategoryOnePrizes(Slot $slot, $participants, $totalAmount)
//    {
//        $multipliers = [3, 1.5, 1];
//        $fixedAmount = $slot->amount/2;
//
//        $winners = $participants->shuffle()->take($slot->member_limit);
//        $rank = 1;
//
//        foreach ($winners as $winner) {
//            $prize = 0;
//            if ($rank <= 3) {
//                $prize = $totalAmount * ($multipliers[$rank - 1] / 5.5);
//            } elseif ($rank >= 4 && $rank <= 10) {
//                $prize = $fixedAmount;
//            }
//
//            Winner::create([
//                'user_id' => $winner->user_id,
//                'slot_id' => $slot->id,
//                'ticket_id' => $winner->id,
//                'winning_amount' => $prize
//            ]);
//
//            $winner->user->increment('wallet_balance', $prize);
//            $rank++;
//        }
//    }

    private function distributeCategoryOnePrizes(Slot $slot, $participants, $totalAmount)
    {
        $multipliers = [3, 1.5, 1]; // Top 3 prize multipliers
        $memberLimit = $slot->member_limit; // Dynamic participant count
        $commission = 1000; // Set your commission (can be 500, 1000, etc.)
        $fixedAmount = $slot->amount / 2; // Fixed prize for remaining winners

        // Calculate dynamic divisor
        $sumMultipliers = array_sum($multipliers); // 3 + 1.5 + 1 = 5.5
        $extraCost = ($memberLimit - 3) * $fixedAmount + $commission;
        $dynamicDivisor = $sumMultipliers + ($extraCost / $totalAmount);

        // Shuffle winners and pick top ones
        $winners = $participants->shuffle()->take($memberLimit);
        $rank = 1;

        foreach ($winners as $winner) {
            $prize = 0;

            if ($rank <= 3) {
                // Prize based on multiplier
                $prize = $totalAmount * ($multipliers[$rank - 1] / $dynamicDivisor);
            } elseif ($rank > 3) {
                // Fixed prize for remaining winners
                $prize = $fixedAmount;
            }

            // Create winner record
            Winner::create([
                'user_id' => $winner->user_id,
                'slot_id' => $slot->id,
                'ticket_id' => $winner->id,
                'winning_amount' => $prize
            ]);

            // Add to user's wallet
            $winner->user->increment('wallet_balance', $prize);
            $rank++;
        }
    }


    private function distributeCategoryTwoPrizes(Slot $slot)
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
            'is_winner' => 1,
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
//        return $slot->amount * $slot->member_limit * 0.8;
        return $slot->amount * $slot->member_limit * ($slot->winning_percentage/100);

    }
}
