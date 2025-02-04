<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            $slot = Slot::find($request->slot_id);

            if($user->wallet_balance < $slot->amount) {
                abort(400, 'Insufficient balance');
            }

            // Deduct amount
            $user->wallet_balance -= $slot->amount;
            $user->save();

            // Create ticket
            $ticket = $slot->tickets()->create([
                'user_id' => $user->id,
                'ticket_number' => Str::uuid()
            ]);

            // Check if slot is full
            if ($slot->isFull()) {
                $this->createNewSlot($slot);
                $this->scheduleWinnerSelection($slot);
            }

            return response()->json($ticket);
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
