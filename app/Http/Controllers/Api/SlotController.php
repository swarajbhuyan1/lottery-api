<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AutoCancelSlot;
use App\Jobs\SelectSlotWinner;
use App\Models\Slot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SlotController extends Controller
{
    public function join(Request $request)
    {
        $request->validate(['slot_id' => 'required|exists:slots,id']);

        return DB::transaction(function () use ($request) {
            $user = $request->user();
            $slot = Slot::lockForUpdate()->findOrFail($request->slot_id);

            // 1. Validate slot status first
            if ($slot->status !== 'active') {
                $message = match($slot->status) {
                    'pending' => 'Slot is full',
                    'processing' => 'Winner selection in progress',
                    'completed' => 'Slot is already closed',
                    default => 'Slot is unavailable or cancelled',
                };

                return response()->json([
                    'success' => false,
                    'message' => $message
                ], 400);
            }

            // 2. Check existing participation
            if ($slot->tickets()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a ticket in this slot'
                ], 400);
            }

            // 3. Check balance after status validation
            if ($user->wallet_balance < $slot->amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance',
                    'required' => $slot->amount,
                    'current' => $user->wallet_balance
                ], 400);
            }

            // All validations passed - proceed with changes
            $user->wallet_balance -= $slot->amount;
            $user->save();

            $ticket = $slot->tickets()->create([
                'user_id' => $user->id,
                'ticket_number' => Str::uuid()
            ]);
            $this->scheduleCancelSlot($slot);

            if ($slot->refresh()->isFull()) {
                $slot->update(['status' => 'pending']);
                $this->createNewSlot($slot);
                $this->scheduleWinnerSelection($slot);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully joined the slot',
                'ticket' => $ticket,
                'new_balance' => $user->wallet_balance
            ]);
        });
    }

    private function createNewSlot(Slot $originalSlot)
    {
        $newSlot = Slot::create([
            'amount' => $originalSlot->amount,
            'member_limit' => $originalSlot->member_limit,
            'start_time' => now(),
            'status' => 'active'
        ]);
    }

    private function scheduleWinnerSelection(Slot $slot)
    {
        // Initial dispatch with 30-second delay
        SelectSlotWinner::dispatch($slot, 'initial')
            ->delay(now()->addMinutes(1))
            ->onQueue('slot_winners');
    }
    private function scheduleCancelSlot($slot)
    {
        // Schedule auto-cancel with 1 day delay
        AutoCancelSlot::dispatch($slot)
            ->delay(now()->addHours(1))
            ->onQueue('slot_winners');

    }
}
