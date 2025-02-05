<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SelectSlotWinner;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ProcessSlotWinner;


class SlotController extends Controller
{
    public function join(Request $request)
    {
        $request->validate(['slot_id' => 'required|exists:slots,id']);

        return DB::transaction(function () use ($request) {
            $user = $request->user();
            $slot = Slot::lockForUpdate()->find($request->slot_id); // Lock the slot row to prevent race conditions

            if ($user->wallet_balance < $slot->amount) {
                abort(400, 'Insufficient balance');
            }

            // Deduct amount from user wallet
            $user->wallet_balance -= $slot->amount;
            $user->save();

            // Create a ticket for the user
            $ticket = $slot->tickets()->create([
                'user_id' => $user->id,
                'ticket_number' => Str::uuid()
            ]);

            // Refresh slot to get updated ticket count
            $slot->refresh();

            // Check if the slot is now full AFTER inserting the ticket
            if ($slot->isFull()) {
                $slot->update(['status' => 'full']);
                $this->createNewSlot($slot);
                $this->scheduleWinnerSelection($slot);
            }

            return response()->json([
                'message' => 'Successfully joined the slot.',
                'ticket' => $ticket
            ]);
        });
    }


    private function createNewSlot(Slot $originalSlot)
    {
        Slot::create([
            'amount' => $originalSlot->amount,
            'member_limit' => $originalSlot->member_limit,
            'start_time' => now(),
            'end_time' => now()->addDay(),
            'status' => 'active'
        ]);
    }

    private function scheduleWinnerSelection(Slot $slot)
    {
        // Schedule winner selection 30 minutes after slot is filled
        $delay = now()->addMinutes(30);

        Queue::later($delay, new SelectSlotWinner($slot));

        // Optional: Update slot status
        $slot->update([
            'winner_selection_scheduled_at' => now(),
            'status' => 'processing'
        ]);
    }
}
