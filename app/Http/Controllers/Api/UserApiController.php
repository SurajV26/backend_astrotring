<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;
use App\Models\CallSession;
use App\Models\ChatSession;
use App\Services\AstrologyChartService;
use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiChatTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UserApiController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'nullable|email|unique:users,email',
            'mobile'   => 'required|digits:10|unique:users,mobile',
            'country_code' => 'nullable|string|max:5',
            'profile_image' => 'nullable|string',
            'terms_accepted' => 'required|in:0,1',
            'dob' => 'nullable|date',
            'birth_time' => 'nullable|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'birth_place' => 'nullable|array',
            'birth_place.displayName' => 'nullable|string',
            'birth_place.place' => 'nullable|string',
            'birth_place.country' => 'nullable|string',
            'birth_place.state' => 'nullable|string',
            'birth_place.latitude' => 'nullable|numeric',
            'birth_place.longitude' => 'nullable|numeric',
            'birth_place.timezone' => 'nullable|numeric',
            'birth_place.elevation' => 'nullable|numeric',
            'gender' => 'nullable|in:male,female,other',
            'marital_status' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $username = $request->mobile;

            if (User::where('username', $username)->exists()) {
                $username = $request->mobile . rand(100,999);
            }

            $autoPassword = substr($request->mobile, -4) . now()->format('dmY');

            $email = $request->email;

            if (!$email) {

                $baseEmail = strtolower(
                    preg_replace('/[^a-zA-Z0-9]/', '', $request->name)
                );

                $email = $baseEmail . time() . rand(100,999) . '@gmail.com';
            }

            $user = User::create([
                'type' => 'user',
                'role_id' => 3,
                'code' => $this->generateUserCode($request->name),
                'terms_accepted' => $request->terms_accepted,

                'name' => $request->name,
                'email' => strtolower($email),
                'mobile' => $request->mobile,
                'country_code' => $request->country_code ?? '+91',
                'username' => $username,
                'password' => bcrypt($autoPassword),

                'dob' => $request->dob,
                'birth_time' => $request->birth_time,
                'birth_place' => $request->birth_place,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
                'occupation' => $request->occupation,

                'status' => 1,
                'otp' => null,
                'otp_created_at' => null,
                'last_otp_sent_at' => null,
                'otp_attempts' => 0,
            ]);

            if ($request->filled('profile_image')) {

                $user->profile_image = $this->saveBase64Image(
                    $request->profile_image,
                    'user'
                );

                $user->save();
            }

            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'total_added' => 0,
                'total_spent' => 0,
            ]);

            try {

                app(\App\Services\AstrologyChartService::class)
                    ->generate($user);

            } catch (\Throwable $e) {

                \Log::error('Astrology Generation Failed', [

                    'user_id' => $user->id,

                    'message' => $e->getMessage()

                ]);

            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Sign Up Successfully. Please login to receive OTP.',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            \Log::error('Registration Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
            ->where('type', 'user')
            ->first();

        if (!$user) {

            return response()->json([
                'status' => false,
                'message' => 'Account not found',
                'new_user' => true
            ], 404);
        }

        if (!$user->status) {
            return response()->json([
                'status' => false,
                'message' => 'Account inactive',
            ], 403);
        }

        if (
            $user->last_otp_sent_at &&
            now()->diffInSeconds($user->last_otp_sent_at) < 60
        ) {

            return response()->json([
                'status' => false,
                'message' => 'Please wait before requesting another OTP',
            ], 429);
        }

        $otp = random_int(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_created_at' => now(),
            'last_otp_sent_at' => now(),
        ]);

        $response = $this->sendOtpSms($request->mobile, $otp);

        $responseData = $response->json();

        if (
            !$response->successful() ||
            !isset($responseData['code']) ||
            $responseData['code'] != '6001'
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP',
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully',
        ]);
    }

    public function verifyLoginOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
            ->where('type', 'user')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->otp_attempts >= 5) {

            return response()->json([
                'status' => false,
                'message' => 'Too many wrong OTP attempts',
            ], 429);
        }

        if ($user->otp != $request->otp) {

            $user->increment('otp_attempts');

            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        if (
            !$user->otp_created_at ||
            \Carbon\Carbon::parse($user->otp_created_at)
                ->diffInMinutes(now()) > 5
        ) {

            $user->update([
                'otp' => null,
                'otp_created_at' => null,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'OTP expired',
            ], 400);
        }

        $user->update([
            'otp' => null,
            'otp_created_at' => null,
            'otp_attempts' => 0,
            'is_online' => 1,
            'last_seen_at' => now(),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function profile(Request $request)
    {
        $user = auth()->user()->load(['wallet']);

        $perPage = (int) $request->get('per_page', 20);
        // call_page, chat_page, recharge_page, reviews_page

        /* ================= TODAY SUMMARY ================= */
        $todayCall = CallSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('started_at', today())
            ->sum('amount');

        $todayChat = AiChatTransaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->whereDate('created_at', today())
            ->sum('amount');

        $todayRecharge = DB::table('wallet_recharges')
            ->where('wallet_id', optional($user->wallet)->id)
            ->whereDate('recharged_at', today())
            ->sum('amount');

        /* ================= LIFETIME SUMMARY ================= */
        $totalCall = CallSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalChat = AiChatTransaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->sum('amount');

        $totalRecharge = DB::table('wallet_recharges')
            ->where('wallet_id', optional($user->wallet)->id)
            ->sum('amount');

        /* ================= CALL HISTORY (PAGINATED) ================= */
        $callPaginator = CallSession::where('user_id', $user->id)
            ->with('astrologer:id,name,code')
            ->orderByDesc('started_at')
            ->paginate($perPage, ['*'], 'call_page');

        $callPaginator->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'astrologer_name' => $c->astrologer->name ?? null,
                'astrologer_code' => $c->astrologer->code ?? null,
                'started_at' => $c->started_at,
                'ended_at' => $c->ended_at,
                'duration_minutes' => $c->duration,
                'amount' => (float) $c->amount,
                'status' => $c->status,
            ];
        });

        /* ================= CHAT HISTORY (PAGINATED) ================= */
        $chatPaginator = AiChatSession::with([
            'astrologer:id,name,slug,image',
            'expertise:id,ai_astrologer_id,name,slug',
            'messages.question:id,question',
            'messages:id,session_id,question_id,sender,message,is_free,charged_amount,model,tokens_used,created_at',
            'transactions:id,user_id,session_id,message_id,amount,balance_before,balance_after,type,remark,created_at'
        ])
        ->where('user_id', $user->id)
        ->latest()
        ->paginate($perPage, ['*'], 'chat_page');

        $chatPaginator->getCollection()->transform(function ($session) {

            return [

                'session_id' => $session->id,

                'astrologer' => [
                    'id' => $session->astrologer?->id,
                    'name' => $session->astrologer?->name,
                    'slug' => $session->astrologer?->slug,
                    'image' => $session->astrologer?->image
                        ? asset('storage/aiAstrologers/'.$session->astrologer->image)
                        : null,
                ],

                'expertise' => [
                    'id' => $session->expertise?->id,
                    'name' => $session->expertise?->name,
                    'slug' => $session->expertise?->slug,
                ],

                'free_messages_used' => $session->free_messages_used,
                'paid_messages' => $session->paid_messages,
                'total_amount' => (float) $session->total_amount,

                'status' => $session->status,
                'started_at' => $session->started_at,
                'last_message_at' => $session->last_message_at,
                'closed_at' => $session->closed_at,

                'messages' => $session->messages->map(function ($message) {

                    return [
                        'id' => $message->id,
                        'question_id' => $message->question_id,
                        'question' => optional($message->question)->question,
                        'sender' => $message->sender,
                        'message' => $message->message,
                        'is_free' => (bool) $message->is_free,
                        'charged_amount' => (float) $message->charged_amount,
                        'model' => $message->model,
                        'tokens_used' => $message->tokens_used,
                        'created_at' => $message->created_at,
                    ];

                })->values(),

                'transactions' => $session->transactions->map(function ($transaction) {

                    return [
                        'id' => $transaction->id,
                        'message_id' => $transaction->message_id,
                        'amount' => (float) $transaction->amount,
                        'balance_before' => (float) $transaction->balance_before,
                        'balance_after' => (float) $transaction->balance_after,
                        'type' => $transaction->type,
                        'remark' => $transaction->remark,
                        'created_at' => $transaction->created_at,
                    ];

                })->values(),
            ];
        });

        /* ================= RECHARGE HISTORY (PAGINATED) ================= */
        $rechargeQuery = DB::table('wallet_recharges')
            ->where('wallet_id', optional($user->wallet)->id)
            ->orderByDesc('recharged_at');

        $rechargePaginator = $rechargeQuery->paginate($perPage, ['*'], 'recharge_page');

        // map recharge items to consistent structure
        $rechargePaginator->getCollection()->transform(function ($r) {
            return [
                'id' => $r->id ?? null,
                'amount' => (float) ($r->amount ?? $r->recharge_amount ?? 0),
                'method' => $r->payment_method ?? null,
                'recharged_at' => $r->recharged_at ?? $r->created_at ?? null,
                'status' => $r->status ?? null,
            ];
        });

        /* ================= REVIEWS GIVEN (PAGINATED) ================= */
        $reviewsQuery = Review::where('user_id', $user->id)
            ->with('astrologer:id,name,code')
            ->orderByDesc('created_at');

        $reviewsPaginator = $reviewsQuery->paginate($perPage, ['*'], 'reviews_page');

        $reviewsPaginator->getCollection()->transform(function ($r) {
            return [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'review' => $r->review,
                'astrologer_name' => $r->astrologer->name ?? null,
                'astrologer_code' => $r->astrologer->code ?? null,
                'date' => $r->created_at,
            ];
        });

        /* ================= RESPONSE ================= */
        return response()->json([
            'status' => true,

            'user' => [
                'id' => $user->id,
                'code' => $user->code,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'country_code' => $user->country_code,
                'gender' => $user->gender,
                'marital_status' => $user->marital_status,
                'occupation' => $user->occupation,
                'dob' => $user->dob,
                'birth_time' => $user->birth_time,
                'birth_place' => $user->birth_place,
                'about' => $user->about,
                'address' => $user->address,
                'pincode' => $user->pincode,
                'profile_image' => $user->profile_image ? asset('storage/user/'.$user->profile_image) : null,
            ],

            'wallet' => [
                'balance' => (float) ($user->wallet?->balance ?? 0),
                'total_added' => (float) ($user->wallet?->total_added ?? 0),
                'total_spent' => (float) ($user->wallet?->total_spent ?? 0),
                'last_recharge_amount' => (float) ($user->wallet?->last_recharge_amount ?? 0),
                'last_recharge_at' => $user->wallet?->last_recharge_at,
            ],

            'today_summary' => [
                'call_spent' => (float) $todayCall,
                'chat_spent' => (float) $todayChat,
                'total_spent' => (float) ($todayCall + $todayChat),
                'recharged' => (float) $todayRecharge,
            ],

            'lifetime_summary' => [
                'call_spent' => (float) $totalCall,
                'chat_spent' => (float) $totalChat,
                'total_spent' => (float) ($totalCall + $totalChat),
                'total_recharged' => (float) $totalRecharge,
            ],

            // paginated sections
            'call_history' => [
                'data' => $callPaginator->items(),
                'meta' => [
                    'current_page' => $callPaginator->currentPage(),
                    'last_page' => $callPaginator->lastPage(),
                    'per_page' => $callPaginator->perPage(),
                    'total' => $callPaginator->total(),
                ],
            ],

            'chat_history' => [
                'data' => $chatPaginator->items(),
                'meta' => [
                    'current_page' => $chatPaginator->currentPage(),
                    'last_page' => $chatPaginator->lastPage(),
                    'per_page' => $chatPaginator->perPage(),
                    'total' => $chatPaginator->total(),
                ],
            ],

            'recharge_history' => [
                'data' => $rechargePaginator->items(),
                'meta' => [
                    'current_page' => $rechargePaginator->currentPage(),
                    'last_page' => $rechargePaginator->lastPage(),
                    'per_page' => $rechargePaginator->perPage(),
                    'total' => $rechargePaginator->total(),
                ],
            ],

            'reviews_given' => [
                'data' => $reviewsPaginator->items(),
                'meta' => [
                    'current_page' => $reviewsPaginator->currentPage(),
                    'last_page' => $reviewsPaginator->lastPage(),
                    'per_page' => $reviewsPaginator->perPage(),
                    'total' => $reviewsPaginator->total(),
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [

            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'mobile' => 'nullable|digits:10|unique:users,mobile,' . $user->id,
            'country_code' => 'nullable|string|max:5',

            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'marital_status' => 'nullable|string|max:100',
            'occupation' => 'nullable|string|max:255',

            'birth_time' => 'nullable|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'birth_place' => 'nullable|array',
            'birth_place.displayName' => 'nullable|string',
            'birth_place.place' => 'nullable|string',
            'birth_place.country' => 'nullable|string',
            'birth_place.state' => 'nullable|string',
            'birth_place.latitude' => 'nullable|numeric',
            'birth_place.longitude' => 'nullable|numeric',
            'birth_place.timezone' => 'nullable|numeric',
            'birth_place.elevation' => 'nullable|numeric',

            'about' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:2000',
            'pincode' => 'nullable|string|max:10',

            'astrologer_id' => 'nullable|exists:users,id',
            'rating'        => 'nullable|integer|min:1|max:5',
            'review'        => 'nullable|string|max:2000',

            'profile_image' => 'nullable|string|max:6000000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        \DB::beginTransaction();

        try {

            if ($request->filled('profile_image')) {
                $user->profile_image = $this->saveBase64Image(
                    $request->profile_image,
                    'user',
                    $user->profile_image
                );
            }

            if ($request->has('name'))        $user->name = $request->name;
            if ($request->has('email'))       $user->email = $request->email;
            if ($request->has('mobile'))      $user->mobile = $request->mobile;
            if ($request->has('country_code')) $user->country_code = $request->country_code;

            if ($request->has('gender'))      $user->gender = $request->gender;
            if ($request->has('dob'))         $user->dob = $request->dob;
            if ($request->has('birth_place')) $user->birth_place = $request->birth_place;

            if ($request->has('birth_time')) {
                $time = $request->birth_time;
                if (strlen($time) === 5) { // HH:MM → HH:MM:00
                    $time .= ':00';
                }
                $user->birth_time = $time;
            }

            if ($request->has('marital_status')) {
                $user->marital_status = $request->marital_status;
            }

            if ($request->has('occupation')) {
                $user->occupation = $request->occupation;
            }

            if ($request->has('about'))   $user->about = $request->about;
            if ($request->has('address')) $user->address = $request->address;
            if ($request->has('pincode')) $user->pincode = $request->pincode;

            $user->modified_by = $user->id;
            $user->save();

            try {

                app(\App\Services\AstrologyChartService::class)
                    ->generate($user);

            } catch (\Throwable $e) {

                \Log::error('Astrology Regeneration Failed', [

                    'user_id' => $user->id,

                    'message' => $e->getMessage()

                ]);

            }

            if ($request->filled('astrologer_id') && $request->filled('rating')) {

                $astrologer = User::where('id', $request->astrologer_id)
                    ->where('type', 'astro')
                    ->first();

                if (! $astrologer) {
                    throw new \Exception('Invalid astrologer');
                }

                Review::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'astrologer_id' => $astrologer->id,
                    ],
                    [
                        'rating' => $request->rating,
                        'review' => $request->review,
                    ]
                );

                $stats = Review::where('astrologer_id', $astrologer->id)
                    ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
                    ->first();

                $astrologer->update([
                    'rating' => round($stats->avg_rating, 2),
                    'rating_count' => $stats->total,
                ]);
            }

            \DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully',
                'user' => $user->fresh(),
            ]);

        } catch (\Throwable $e) {

            \DB::rollBack();

            \Log::error('Profile Update Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = auth()->user();

        if (! Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Old password does not match',
            ], 401);
        }

        $user->update([
            'password' => bcrypt($request->new_password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $user->update(['is_online' => 0, 'last_seen_at' => now()]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logout successful',
        ]);
    }

    public function delete()
    {
        $user = auth()->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'Account deleted successfully',
        ]);
    }

    private function generateUserCode($name)
    {
        $prefix = strtoupper(substr(trim($name), 0, 3));
        $last = User::where('code', 'like', $prefix.'%')->latest()->first();
        $num = $last && preg_match('/'.$prefix.'(\d+)/', $last->code, $m)
            ? (int)$m[1] + 1
            : 1;

        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    private function saveBase64Image($base64, $folder = 'user', $oldFile = null)
    {
        $storagePath = storage_path("app/public/{$folder}");
        $publicPath  = public_path("storage/{$folder}");

        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        if ($oldFile) {
            if (file_exists($storagePath.'/'.$oldFile)) {
                unlink($storagePath.'/'.$oldFile);
            }
            if (file_exists($publicPath.'/'.$oldFile)) {
                unlink($publicPath.'/'.$oldFile);
            }
        }

        preg_match('/^data:image\/(\w+);base64,/', $base64);
        $data = base64_decode(substr($base64, strpos($base64, ',') + 1));

        $filename = uniqid().'.webp';

        // Save in storage
        Image::make($data)
            ->fit(128,128)
            ->encode('webp',80)
            ->save($storagePath.'/'.$filename);

        // Copy to public
        copy($storagePath.'/'.$filename, $publicPath.'/'.$filename);

        return $filename;
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type'  => 'required|in:astro,user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('type', $request->type)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Account not found for this type',
            ], 404);
        }

        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp,
        ]);

        $mailData = [
            'name' => $user->name,
            'otp'  => $otp,
        ];

        \Mail::to($user->email)
            ->send(new \App\Mail\ForgotPasswordMail($mailData));

        return response()->json([
            'status' => true,
            'message' => 'OTP sent to your email',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type'  => 'required|in:astro,user',
            'otp'   => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('type', $request->type)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid account',
            ], 404);
        }

        if ($user->otp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid OTP',
            ], 400);
        }

        if ($user->updated_at->diffInMinutes(now()) > 10) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP verified successfully',
        ]);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('mobile', $request->mobile)
            ->where('type', 'user')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (
            $user->last_otp_sent_at &&
            now()->diffInSeconds($user->last_otp_sent_at) < 60
        ) {

            return response()->json([
                'status' => false,
                'message' => 'Please wait before requesting another OTP',
            ], 429);
        }

        $otp = random_int(100000, 999999);

        $user->update([
            'otp' => $otp,
            'otp_created_at' => now(),
            'last_otp_sent_at' => now(),
        ]);

        $response = $this->sendOtpSms($request->mobile, $otp);

        $responseData = $response->json();

        if (
            !$response->successful() ||
            !isset($responseData['code']) ||
            $responseData['code'] != '6001'
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP',
                'sms_response' => $response->body(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP resent successfully',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type'  => 'required|in:astro,user',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('email', $request->email)
                    ->where('type', $request->type)
                    ->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid account',
            ], 404);
        }

        $user->update([
            'password' => bcrypt($request->password),
            'otp' => null,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully',
        ]);
    }

    private function sendOtpSms($mobile, $otp)
    {
        $message = "Dear customer, {$otp} is the OTP for your login at www.astrotring.com - Astrotring Veltex";

        $params = [
            'username'   => config('services.sms.username'),
            'dest'       => $mobile,
            'apikey'     => config('services.sms.api_key'),
            'signature'  => config('services.sms.sender'),
            'msgtype'    => 'PM',
            'msgtxt'     => $message,
            'entityid'   => config('services.sms.entity_id'),
            'templateid' => config('services.sms.template_id'),
        ];

        $response = Http::timeout(30)->get(
            config('services.sms.base_url'),
            $params
        );

        \Log::info('SMS API REQUEST', [
            'params' => $params,
        ]);

        \Log::info('SMS API RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $response;
    }
}
