<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletRecharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayPaymentController extends Controller
{
    protected $isTest = false; // true = test | false = live

    public function createOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        try {
            $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

            $order = $api->order->create([
                'receipt' => 'rcpt_' . uniqid(),
                'amount' => (int) ($request->amount * 100), // paise
                'currency' => 'INR'
            ]);

            return response()->json([
                'status' => true,
                'order_id' => $order['id'],
                'amount' => $request->amount
            ]);

        } catch (\Exception $e) {

            Log::error('Create Order Error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => $this->isTest ? $e->getMessage() : 'Unable to create order'
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        $request->validate([
            'razorpay_order_id' => 'required',
            'razorpay_payment_id' => 'required',
            'razorpay_signature' => 'required',
            'amount' => $this->isTest ? 'required|numeric|min:1' : 'nullable'
        ]);

        DB::beginTransaction();

        try {

            $user = $request->user();

            // 🔥 Duplicate / idempotency check
            $existing = Payment::where('transaction_id', $request->razorpay_payment_id)->first();

            if ($existing) {
                DB::commit();
                return response()->json([
                    'status' => true,
                    'message' => 'Payment already processed',
                    'data' => [
                        'amount' => $existing->amount
                    ]
                ]);
            }

            $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

            // =========================
            // 🔥 MODE SWITCH
            // =========================

            if ($this->isTest) {
                // 🧪 TEST MODE
                $amount = (float) $request->amount;
                $paymentMode = 'test';
                $paymentData = $request->all();

            } else {
                // 🚀 LIVE MODE

                // 🔐 Signature verify
                $api->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $request->razorpay_order_id,
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature' => $request->razorpay_signature
                ]);

                // 🔎 Fetch real payment
                $paymentData = $api->payment->fetch($request->razorpay_payment_id);

                // ❗ Ensure captured (success)
                if (($paymentData['status'] ?? '') !== 'captured') {
                    throw new \Exception('Payment not captured');
                }

                // paise → rupees
                $amount = ((int) $paymentData['amount']) / 100;
                $paymentMode = $paymentData['method'] ?? 'online';
            }

            // 🔥 SAVE PAYMENT
            $payment = Payment::create([
                'user_id' => $user->id,
                'platform' => 'astrotring',
                'order_id' => $request->razorpay_order_id,
                'payment_gateway' => 'razorpay',
                'transaction_id' => $request->razorpay_payment_id,
                'amount' => $amount,
                'currency' => 'INR',
                'payment_status' => 'success',

                'customer_email' => $user->email,
                'customer_phone' => trim(($user->country_code ?? '') . ($user->mobile ?? '')),

                'payment_mode' => $paymentMode,

                'payment_request_data' => $request->all(),
                'payment_response_data' => $paymentData
            ]);

            // 🔥 WALLET FETCH / CREATE
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'total_added' => 0,
                    'total_spent' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0
                ]
            );

            $before = (float) $wallet->balance;
            $after  = $before + $amount;

            // 🔥 WALLET RECHARGE ENTRY
            WalletRecharge::create([
                'wallet_id' => $wallet->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'payment_method' => 'razorpay',
                'gateway_txn_id' => $request->razorpay_payment_id,
                'recharged_at' => now()
            ]);

            // 🔥 UPDATE WALLET
            $wallet->update([
                'balance' => $after,
                'total_added' => $wallet->total_added + $amount,
                'last_recharge_amount' => $amount,
                'last_recharge_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Recharge successful',
                'data' => [
                    'amount' => $amount,
                    'balance' => $after
                ]
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            // 🔥 Full debug log
            Log::error('Payment Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => $this->isTest
                    ? $e->getMessage()
                    : 'Payment failed, please try again'
            ], 500);
        }
    }
}