<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiChatTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletApiController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0]
        );

        return response()->json([
            'success' => true,
            'data' =>[
                'balance' => (float) $wallet->balance,
                'locked_balance' => (float) $wallet->locked_balance,
                'total_added' => (float) $wallet->total_added,
                'total_spent' => (float) $wallet->total_spent,
                'total_earned' => (float) $wallet->total_earned,
                'total_withdrawn' => (float) $wallet->total_withdrawn,
                'last_recharge_amount' => (float) $wallet->last_recharge_amount,
                'last_recharge_at' => $wallet->last_recharge_at,
                'wallet_age' => optional($wallet->created_at)->diffForHumans(),
                'created_at' => optional($wallet->created_at)->format('d M Y h:i A'),
            ],
            'user' => [
                'id' => $wallet->user->id,
                'name' => $wallet->user->name,
                'email' => $wallet->user->email,
                'mobile' => $wallet->user->mobile,
            ]
        ]);
    }

    public function chatStatistics(Request $request)
    {
        $user = $request->user();

        /*
        * Get ALL chat sessions of the logged-in user.
        *
        * Do NOT paginate here because the requirement is complete
        * chat history + lifetime statistics.
        */
        $sessions = AiChatSession::with([
                'astrologer:id,name,slug',
                'expertise:id,ai_astrologer_id,name,slug',
            ])
            ->withCount([
                // All messages in the session
                'messages as total_messages',

                // User messages only
                'messages as questions_asked' => function ($query) {
                    $query->where('sender', 'user');
                },

                // Assistant replies only
                'messages as assistant_replies' => function ($query) {
                    $query->where('sender', 'assistant');
                },

                // Free user messages
                'messages as free_messages' => function ($query) {
                    $query->where('sender', 'user')
                        ->where('is_free', true);
                },

                // Paid user messages
                'messages as paid_messages_count' => function ($query) {
                    $query->where('sender', 'user')
                        ->where('is_free', false);
                },
            ])
            ->withSum([
                'transactions as total_deducted' => function ($query) {
                    $query->where('type', 'debit');
                }
            ], 'amount')
            ->where('user_id', $user->id)
            ->orderByDesc('started_at')
            ->get();

        /*
        * Lifetime summary
        *
        * IMPORTANT:
        * Do not calculate summary from the current page/session collection.
        * These queries cover the user's COMPLETE chat history.
        */

        $totalChats = $sessions->count();

        $totalFreeMessages = AiChatMessage::whereHas('session', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('sender', 'user')
            ->where('is_free', true)
            ->count();

        $totalPaidMessages = AiChatMessage::whereHas('session', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('sender', 'user')
            ->where('is_free', false)
            ->count();

        /*
        * Total paid minutes are stored on every permanent session.
        */
        $totalPaidMinutes = (int) AiChatSession::where('user_id', $user->id)
            ->sum('chat_billed_minutes');

        /*
        * Total money spent is calculated from actual debit transactions.
        * This is more reliable than summing session total_amount.
        */
        $totalSpent = (float) AiChatTransaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->sum('amount');

        /*
        * Build complete history.
        */
        $history = $sessions->map(function ($session) {

            $startedAt = $session->started_at
                ? Carbon::parse($session->started_at)
                : null;

            $lastMessageAt = $session->last_message_at
                ? Carbon::parse($session->last_message_at)
                : null;

            /*
            * Duration here means the span between the first session
            * creation and the latest conversation activity.
            *
            * Billing duration is separately available through
            * chat_billed_minutes.
            */
            $duration = ($startedAt && $lastMessageAt)
                ? $startedAt->diffForHumans($lastMessageAt, true)
                : null;

            return [

                'session_id' => $session->id,

                'astrologer' => [
                    'id' => $session->astrologer?->id,
                    'name' => $session->astrologer?->name,
                    'slug' => $session->astrologer?->slug,
                ],

                'expertise' => [
                    'id' => $session->expertise?->id,
                    'name' => $session->expertise?->name,
                    'slug' => $session->expertise?->slug,
                ],

                /*
                * Complete conversation period
                */
                'started_at' => $startedAt
                    ? $startedAt->format('d M Y h:i A')
                    : null,

                'last_message_at' => $lastMessageAt
                    ? $lastMessageAt->format('d M Y h:i A')
                    : null,

                'duration' => $duration,

                /*
                * Message statistics
                */
                'questions_asked' => (int) $session->questions_asked,

                'assistant_replies' => (int) $session->assistant_replies,

                'free_messages' => (int) $session->free_messages,

                'paid_messages' => (int) $session->paid_messages_count,

                'total_messages' => (int) $session->total_messages,

                /*
                * Billing statistics
                */
                // 'paid_minutes' => (int) $session->chat_billed_minutes,

                'total_deducted' => (float) ($session->total_deducted ?? 0),

                'session_amount' => (float) $session->total_amount,

            ];
        })->values();

        return response()->json([

            'status' => true,

            'message' => 'Chat statistics fetched successfully',

            /*
            * LIFETIME TOTALS
            */
            'summary' => [

                'total_chats' => (int) $totalChats,

                'total_free_messages' => (int) $totalFreeMessages,

                'total_paid_messages' => (int) $totalPaidMessages,

                // 'total_paid_minutes' => (int) $totalPaidMinutes,

                'total_spent' => round($totalSpent, 2),

            ],

            /*
            * COMPLETE CHAT HISTORY
            */
            'history' => $history,

        ]);
    }

    public function recharge(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:upi,card,netbanking',
            'gateway_txn_id' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();

        DB::transaction(function () use ($request, $user) {

            $wallet = Wallet::where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrCreate(['user_id' => $user->id]);

            $before = $wallet->balance;
            $amount = $request->amount;

            $wallet->balance += $amount;
            $wallet->total_added += $amount;
            $wallet->last_recharge_amount = $amount;
            $wallet->last_recharge_at = now();
            $wallet->save();

            WalletRecharge::create([
                'wallet_id'      => $wallet->id,
                'amount'         => $amount,
                'balance_before' => $before,
                'balance_after'  => $wallet->balance,
                'payment_method' => $request->payment_method,
                'gateway_txn_id' => $request->gateway_txn_id,
                'recharged_at'   => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Wallet recharged successfully'
        ], 201);
    }

    public function rechargeHistory(Request $request)
    {
        $user = auth()->user();

        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $history = WalletRecharge::where('wallet_id', $wallet->id)
            ->orderByDesc('recharged_at')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
}
