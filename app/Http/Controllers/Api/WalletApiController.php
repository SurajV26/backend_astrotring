<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiChatTransaction;
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
        $user = auth()->user();

        $sessions = AiChatSession::with([
                'astrologer:id,name,slug',
                'expertise:id,ai_astrologer_id,name,slug',
                'messages:id,session_id,sender',
                'transactions:id,session_id,amount,type'
            ])
            ->where('user_id', $user->id)
            ->latest('last_message_at')
            ->paginate(10);

        $totalPaidChats = $sessions->getCollection()->sum('paid_messages');

        $totalFreeChats = $sessions->getCollection()->sum('free_messages_used');

        $totalSpent = $sessions->getCollection()->sum(function ($session) {
            return $session->transactions
                ->where('type', 'debit')
                ->sum('amount');
        });

        $history = $sessions->getCollection()->map(function ($session) {

            $questionCount = $session->messages
                ->where('sender', 'user')
                ->count();

            $replyCount = $session->messages
                ->where('sender', 'assistant')
                ->count();

            $deducted = $session->transactions
                ->where('type', 'debit')
                ->sum('amount');

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

                'questions_asked' => $questionCount,

                'assistant_replies' => $replyCount,

                'free_messages' => $session->free_messages_used,

                'paid_messages' => $session->paid_messages,

                'total_messages' => $questionCount + $replyCount,

                'total_deducted' => (float)$deducted,

                'session_amount' => (float)$session->total_amount,

                'started_at' => optional($session->started_at)
                    ->format('d M Y h:i A'),

                'last_message_at' => optional($session->last_message_at)
                    ->format('d M Y h:i A'),

                'duration' => $session->started_at && $session->last_message_at
                    ? $session->started_at->diffForHumans($session->last_message_at, true)
                    : null,

            ];

        });

        return response()->json([

            'status' => true,

            'message' => 'Chat statistics fetched successfully',

            'summary' => [

                'total_paid_chats' => (int) $totalPaidChats,

                'total_free_chats' => (int) $totalFreeChats,

                'total_spent' => (float) $totalSpent,

            ],

            'history' => $history,

            'pagination' => [

                'current_page' => $sessions->currentPage(),

                'last_page' => $sessions->lastPage(),

                'per_page' => $sessions->perPage(),

                'total' => $sessions->total(),

            ]

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
