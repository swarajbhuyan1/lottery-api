<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AutoCancelSlot;
use App\Jobs\SelectSlotWinner;
use App\Models\Slot;
use App\Models\Winner;
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
            'winning_percentage' => $originalSlot->winning_percentage,
            'start_time' => now(),
            'end_time' => now()->addDays(1),
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
            ->delay(now()->addMinutes(1))
            ->onQueue('slot_winners');

    }

    public function participants($slotId)
    {
        $slot = Slot::find($slotId);

        // Check if the slot exists
        if (!$slot) {
            return response()->json([
                'success' => false,
                'message' => 'Slot not found'
            ], 404);
        }

        $participants = $slot->tickets()
            ->with('user:id,name')
            ->get(['id', 'ticket_number', 'user_id', 'created_at'])
            ->map(function ($ticket) {
                // Check if the ticket is in the winners table
                $winner = Winner::where('ticket_id', $ticket->id)->first();

                return [
                    'name' => $ticket->user->name,
                    'ticket_number' => $ticket->ticket_number,
                    'is_winner' => $winner ? 'yes' : 'no',  // If winner exists, it's a winner
                    'winning_amount' => $winner ? $winner->winning_amount : 0, // Show winning amount if exists
                    'joined_at' => $ticket->created_at->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'slot_id' => $slot->id,
            'participants_limit' => $slot->member_limit,
            'status' => $slot->status,
            'amount' => $slot->amount,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'winning_percentage' => $slot->winning_percentage,
            'total_participants' => $participants->count(),
            'participants' => $participants
        ]);
    }

}
