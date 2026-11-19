<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Razorpay\Api\Api;

class RazorpayPaymentController extends Controller
{
    protected bool $isTest = false;  // true = test | false = live

    private const QUESTION_PACKS = [
        'silver' => [
            'name' => 'Silver', 'questions' => 5, 'original_price' => 19.99,
            'discounted_price' => 9.49, 'coupon_code' => 'PAYNOW',
        ],
        'gold' => [
            'name' => 'Gold', 'questions' => 10, 'original_price' => 39.99,
            'discounted_price' => 12.99, 'coupon_code' => 'GQUEST',
        ],
        'diamond' => [
            'name' => 'Diamond', 'questions' => 20, 'original_price' => 79.99,
            'discounted_price' => 17.99, 'coupon_code' => 'GOMAX',
        ],
    ];

    private function razorpayApi(): Api
    {
        $key = config('services.razorpay.key') ?: env('RAZORPAY_KEY');
        $secret = config('services.razorpay.secret') ?: env('RAZORPAY_SECRET');

        if (!$key || !$secret) {
            throw new \RuntimeException('Razorpay credentials are not configured.');
        }

        return new Api($key, $secret);
    }

    /** Existing INR wallet recharge order endpoint. */
    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:500000',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);
        $amountSubunits = (int) round($amount * 100);
        $receipt = 'wlt_' . $user->id . '_' . Str::lower(Str::random(16));

        try {
            $order = $this->razorpayApi()->order->create([
                'receipt' => $receipt,
                'amount' => $amountSubunits,
                'currency' => 'INR',
                'notes' => [
                    'user_id' => (string) $user->id,
                    'purpose' => 'wallet_recharge',
                ],
            ]);

            Payment::create([
                'user_id' => $user->id,
                'platform' => 'astrotring',
                'order_id' => $order['id'],
                'payment_gateway' => 'razorpay',
                'amount' => $amount,
                'currency' => 'INR',
                'payment_status' => 'pending',
                'customer_email' => $user->email,
                'customer_phone' => trim(($user->country_code ?? '') . ($user->mobile ?? '')),
                'payment_request_data' => [
                    'purpose' => 'wallet_recharge',
                    'amount' => $amount,
                    'amount_subunits' => $amountSubunits,
                    'currency' => 'INR',
                ],
                'payment_response_data' => ['order' => ['id' => $order['id']]],
            ]);

            return response()->json([
                'status' => true,
                'order_id' => $order['id'],
                'amount' => $amount,
                'currency' => 'INR',
            ]);
        } catch (\Throwable $e) {
            Log::error('Razorpay wallet order creation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $this->isTest ? $e->getMessage() : 'Unable to create order.',
            ], 500);
        }
    }

    /** Public package definitions; server values, not client-supplied prices, are authoritative. */
    public function questionPacks(): JsonResponse
    {
        $packs = [];
        foreach (self::QUESTION_PACKS as $code => $pack) {
            $packs[] = [
                'pack_code' => $code,
                'name' => $pack['name'],
                'questions' => $pack['questions'],
                'original_price' => number_format($pack['original_price'], 2, '.', ''),
                'discounted_price' => number_format($pack['discounted_price'], 2, '.', ''),
                'currency' => 'USD',
                'coupon_code' => $pack['coupon_code'],
            ];
        }

        return response()->json(['status' => true, 'data' => $packs]);
    }

    /** Create a USD question-pack order, stored in the existing payments table. */
    public function createQuestionPackOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pack_code' => 'required|string|in:silver,gold,diamond',
            'coupon_code' => 'nullable|string|max:32',
        ]);

        $user = $request->user();
        $code = strtolower($validated['pack_code']);
        $pack = self::QUESTION_PACKS[$code];
        $coupon = strtoupper(trim((string) ($validated['coupon_code'] ?? '')));

        if ($coupon !== '' && $coupon !== $pack['coupon_code']) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid coupon code for the selected package.',
            ], 422);
        }

        $discountApplied = $coupon !== '';
        $amount = round($discountApplied ? $pack['discounted_price'] : $pack['original_price'], 2);
        $amountSubunits = (int) round($amount * 100);
        $receipt = 'aqp_' . $user->id . '_' . Str::lower(Str::random(16));

        try {
            $order = $this->razorpayApi()->order->create([
                'receipt' => $receipt,
                'amount' => $amountSubunits,
                'currency' => 'USD',
                'notes' => [
                    'user_id' => (string) $user->id,
                    'purpose' => 'ai_question_pack',
                    'pack_code' => $code,
                    'questions' => (string) $pack['questions'],
                ],
            ]);

            // The pending payment record binds user + pack + currency + price to the Razorpay order.
            Payment::create([
                'user_id' => $user->id,
                'platform' => 'astrotring',
                'order_id' => $order['id'],
                'payment_gateway' => 'razorpay',
                'amount' => $amount,
                'currency' => 'USD',
                'payment_status' => 'pending',
                'customer_email' => $user->email,
                'customer_phone' => trim(($user->country_code ?? '') . ($user->mobile ?? '')),
                'payment_request_data' => [
                    'purpose' => 'ai_question_pack',
                    'pack_code' => $code,
                    'pack_name' => $pack['name'],
                    'questions' => $pack['questions'],
                    'coupon_code' => $discountApplied ? $coupon : null,
                    'original_price' => $pack['original_price'],
                    'discount_amount' => round($pack['original_price'] - $amount, 2),
                    'amount' => $amount,
                    'amount_subunits' => $amountSubunits,
                    'currency' => 'USD',
                ],
                'payment_response_data' => ['order' => ['id' => $order['id']],],
            ]);

            return response()->json([
                'status' => true,
                'order_id' => $order['id'],
                'pack_code' => $code,
                'questions' => $pack['questions'],
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => 'USD',
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Razorpay question pack order creation failed', [
                'user_id' => $user->id,
                'pack_code' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $this->isTest ? $e->getMessage() : 'Unable to create question-pack order.',
            ], 500);
        }
    }

    /** Returns persistent question balance derived from successful payments and used-message records. */
    public function questionBalance(Request $request): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => ['question_balance' => $this->getQuestionPackBalance((int) $request->user()->id)],
        ]);
    }

    /**
     * Shared verification endpoint: wallet recharges remain INR; question-pack purchases are USD.
     * Both use the existing payments table and this existing controller.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string|max:255',
            'razorpay_payment_id' => 'required|string|max:255',
            'razorpay_signature' => $this->isTest ? 'nullable|string' : 'required|string',
            'amount' => $this->isTest ? 'required|numeric|min:0.01' : 'nullable|numeric|min:0.01',
        ]);

        $user = $request->user();

        $paymentRecord = Payment::query()
            ->where('user_id', $user->id)
            ->where('platform', 'astrotring')
            ->where('payment_gateway', 'razorpay')
            ->where('order_id', $request->razorpay_order_id)
            ->first();

        if (!$paymentRecord) {
            return response()->json([
                'status' => false,
                'message' => 'Payment order was not found for this user.',
            ], 422);
        }

        $metadata = $this->decodeJson($paymentRecord->payment_request_data);
        $purpose = $metadata['purpose'] ?? 'wallet_recharge';
        $expectedCurrency = strtoupper((string) $paymentRecord->currency);
        $expectedSubunits = (int) round(((float) $paymentRecord->amount) * 100);

        try {
            if ($this->isTest) {
                $gatewayAmount = (int) round(((float) $request->amount) * 100);
                $paymentData = [
                    'id' => $request->razorpay_payment_id,
                    'order_id' => $request->razorpay_order_id,
                    'status' => 'captured',
                    'amount' => $gatewayAmount,
                    'currency' => $expectedCurrency,
                    'method' => 'test',
                ];
                $paymentMode = 'test';
            } else {
                $api = $this->razorpayApi();
                $api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $request->razorpay_order_id,
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature' => $request->razorpay_signature,
                ]);

                $fetched = $api->payment->fetch($request->razorpay_payment_id);
                $paymentData = [
                    'id' => $fetched['id'],
                    'order_id' => $fetched['order_id'] ?? null,
                    'status' => $fetched['status'] ?? null,
                    'amount' => (int) ($fetched['amount'] ?? 0),
                    'currency' => strtoupper((string) ($fetched['currency'] ?? '')),
                    'method' => $fetched['method'] ?? 'online',
                ];
                $paymentMode = $paymentData['method'];
            }

            if (($paymentData['status'] ?? null) !== 'captured') {
                return response()->json(['status' => false, 'message' => 'Payment has not been captured.'], 422);
            }
            if (($paymentData['order_id'] ?? null) !== $request->razorpay_order_id) {
                return response()->json(['status' => false, 'message' => 'Payment does not match this order.'], 422);
            }
            if (($paymentData['currency'] ?? null) !== $expectedCurrency) {
                return response()->json(['status' => false, 'message' => 'Payment currency does not match the order.'], 422);
            }
            if ((int) $paymentData['amount'] !== $expectedSubunits) {
                return response()->json(['status' => false, 'message' => 'Payment amount does not match the order.'], 422);
            }

            $result = DB::transaction(function () use ($user, $paymentRecord, $paymentData, $paymentMode, $request, $purpose, $metadata, $expectedCurrency) {
                // Serialise payment verification and message-credit consumption for this user.
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $stored = Payment::whereKey($paymentRecord->id)->lockForUpdate()->firstOrFail();

                if ($stored->payment_status === 'success') {
                    if ($stored->transaction_id !== $request->razorpay_payment_id) {
                        throw new \DomainException('This order was already completed with a different payment.');
                    }
                    return [
                        'already_processed' => true,
                        'purpose' => $purpose,
                        'amount' => (float) $stored->amount,
                        'currency' => $stored->currency,
                        'balance' => $purpose === 'ai_question_pack'
                            ? $this->getQuestionPackBalance((int) $user->id)
                            : (float) (Wallet::where('user_id', $user->id)->value('balance') ?? 0),
                        'questions' => (int) ($metadata['questions'] ?? 0),
                    ];
                }

                if ($stored->payment_status !== 'pending') {
                    throw new \DomainException('This payment order is no longer pending.');
                }

                $duplicate = Payment::where('transaction_id', $request->razorpay_payment_id)
                    ->where('id', '!=', $stored->id)
                    ->exists();
                if ($duplicate) {
                    throw new \DomainException('This payment transaction has already been used.');
                }

                if ($purpose === 'ai_question_pack' && $expectedCurrency !== 'USD') {
                    throw new \DomainException('Question packs must be paid in USD.');
                }
                if ($purpose !== 'ai_question_pack' && $expectedCurrency !== 'INR') {
                    throw new \DomainException('Wallet recharge orders must be paid in INR.');
                }

                $responseData = $this->decodeJson($stored->payment_response_data);
                $responseData['verification'] = $paymentData;

                $stored->update([
                    'transaction_id' => $request->razorpay_payment_id,
                    'payment_status' => 'success',
                    'payment_mode' => $paymentMode,
                    'payment_response_data' => $responseData,
                ]);

                if ($purpose === 'ai_question_pack') {
                    return [
                        'already_processed' => false,
                        'purpose' => 'ai_question_pack',
                        'amount' => (float) $stored->amount,
                        'currency' => 'USD',
                        'balance' => $this->getQuestionPackBalance((int) $user->id),
                        'questions' => (int) ($metadata['questions'] ?? 0),
                        'pack_code' => $metadata['pack_code'] ?? null,
                    ];
                }

                // Existing wallet recharge behavior; do not add USD package money to INR wallet.
                $wallet = Wallet::firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0, 'total_added' => 0, 'total_spent' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
                );
                $wallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
                $before = (float) $wallet->balance;
                $amount = (float) $stored->amount;
                $after = round($before + $amount, 2);

                WalletRecharge::create([
                    'wallet_id' => $wallet->id,
                    'payment_id' => $stored->id,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'payment_method' => 'razorpay',
                    'gateway_txn_id' => $request->razorpay_payment_id,
                    'recharged_at' => now(),
                ]);

                $wallet->update([
                    'balance' => $after,
                    'total_added' => (float) $wallet->total_added + $amount,
                    'last_recharge_amount' => $amount,
                    'last_recharge_at' => now(),
                ]);

                return [
                    'already_processed' => false,
                    'purpose' => 'wallet_recharge',
                    'amount' => $amount,
                    'currency' => 'INR',
                    'balance' => $after,
                ];
            });

            if ($result['purpose'] === 'ai_question_pack') {
                return response()->json([
                    'status' => true,
                    'message' => $result['already_processed'] ? 'Payment already processed.' : 'Question pack purchased successfully.',
                    'data' => [
                        'pack_code' => $result['pack_code'] ?? ($metadata['pack_code'] ?? null),
                        'questions_added' => $result['already_processed'] ? 0 : (int) ($result['questions'] ?? 0),
                        'question_balance' => (int) $result['balance'],
                        'amount' => number_format((float) $result['amount'], 2, '.', ''),
                        'currency' => 'USD',
                    ],
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => $result['already_processed'] ? 'Payment already processed.' : 'Recharge successful.',
                'data' => [
                    'amount' => (float) $result['amount'],
                    'currency' => 'INR',
                    'balance' => (float) $result['balance'],
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Razorpay verification failed', [
                'user_id' => $user->id,
                'order_id' => $request->razorpay_order_id,
                'payment_id' => $request->razorpay_payment_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => $this->isTest ? $e->getMessage() : 'Payment verification failed. Please try again.',
            ], 500);
        }
    }

    private function getQuestionPackBalance(int $userId): int
    {
        $purchased = 0;
        $payments = Payment::query()
            ->where('user_id', $userId)
            ->where('platform', 'astrotring')
            ->where('payment_gateway', 'razorpay')
            ->where('payment_status', 'success')
            ->where('currency', 'USD')
            ->get(['payment_request_data']);

        foreach ($payments as $payment) {
            $meta = $this->decodeJson($payment->payment_request_data);
            if (($meta['purpose'] ?? null) === 'ai_question_pack') {
                $purchased += max(0, (int) ($meta['questions'] ?? 0));
            }
        }

        $used = (int) DB::table('ai_chat_messages as m')
            ->join('ai_chat_sessions as s', 's.id', '=', 'm.session_id')
            ->where('s.user_id', $userId)
            ->where('m.sender', 'user')
            ->where('m.question_credit_used', true)
            ->count();

        return max(0, $purchased - $used);
    }

    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return (array) $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
