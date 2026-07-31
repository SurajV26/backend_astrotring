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
        try {

            $user = auth()->user()->load([
                'wallet',
                'reviews.astrologer'
            ]);

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $reviews = $user->reviews
                ->sortByDesc('created_at')
                ->values()
                ->map(function ($review) {

                    return [

                        'review_id' => $review->id,

                        'astrologer' => [

                            'id' => optional($review->astrologer)->id,

                            'code' => optional($review->astrologer)->code,

                            'name' => optional($review->astrologer)->name,

                            'profile_image' => optional($review->astrologer)->profile_image
                                ? asset('storage/user/' . $review->astrologer->profile_image)
                                : null,

                            'rating' => (float) (optional($review->astrologer)->rating ?? 0),

                            'rating_count' => (int) (optional($review->astrologer)->rating_count ?? 0),

                        ],

                        'rating' => (int) $review->rating,

                        'review' => $review->review,

                        'created_at' => $review->created_at->format('d M Y h:i A'),

                        'updated_at' => $review->updated_at->format('d M Y h:i A'),

                    ];

                });

            return response()->json([

                'status' => true,

                'message' => 'Profile fetched successfully',

                'user' => [

                    'id' => $user->id,

                    'code' => $user->code,

                    'username' => $user->username,

                    'name' => $user->name,

                    'email' => $user->email,

                    'mobile' => $user->mobile,

                    'country_code' => $user->country_code,

                    'profile_image' => $user->profile_image
                        ? asset('storage/user/' . $user->profile_image)
                        : null,

                    'gender' => $user->gender,

                    'dob' => $user->dob,

                    'birth_time' => $user->birth_time,

                    'birth_place' => $user->birth_place,

                    'marital_status' => $user->marital_status,

                    'occupation' => $user->occupation,

                    'about' => $user->about,

                    'address' => $user->address,

                    'pincode' => $user->pincode,

                    'status' => (int) $user->status,

                    'is_online' => (int) $user->is_online,

                ],

                'wallet' => [

                    'balance' => (float) ($user->wallet->balance ?? 0),

                    'total_added' => (float) ($user->wallet->total_added ?? 0),

                    'total_spent' => (float) ($user->wallet->total_spent ?? 0),

                    'last_recharge_amount' => (float) ($user->wallet->last_recharge_amount ?? 0),

                    'last_recharge_at' => $user->wallet->last_recharge_at,

                ],

                'reviews' => $reviews,

            ]);

        } catch (\Throwable $e) {

            \Log::error('Profile Error', [

                'message' => $e->getMessage(),

                'file' => $e->getFile(),

                'line' => $e->getLine(),

            ]);

            return response()->json([

                'status' => false,

                'message' => 'Failed to fetch profile',

                'error' => $e->getMessage(),

            ], 500);
        }
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

            'profile_image' => 'nullable|string|max:6000000',

            'astrologer_id' => 'nullable|exists:users,id',
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:2000',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);

        }

        DB::beginTransaction();

        try {

            if ($request->filled('profile_image')) {

                $user->profile_image = $this->saveBase64Image(
                    $request->profile_image,
                    'user',
                    $user->profile_image
                );

            }

            $fields = [

                'name',
                'email',
                'mobile',
                'country_code',
                'gender',
                'dob',
                'birth_place',
                'marital_status',
                'occupation',
                'about',
                'address',
                'pincode',

            ];

            foreach ($fields as $field) {

                if ($request->has($field)) {

                    $user->{$field} = $request->{$field};

                }

            }

            if ($request->has('birth_time')) {

                $time = $request->birth_time;

                if (!empty($time) && strlen($time) == 5) {

                    $time .= ':00';

                }

                $user->birth_time = $time;

            }

            $user->modified_by = $user->id;

            $user->save();

                try {

            app(\App\Services\AstrologyChartService::class)
                ->generate($user);

        } catch (\Throwable $e) {

            \Log::error('Astrology Regeneration Failed', [

                'user_id' => $user->id,
                'message' => $e->getMessage(),

            ]);

        }

        if ($request->filled('astrologer_id') && $request->filled('rating')) {

            $astrologer = User::where('id', $request->astrologer_id)
                ->where('type', 'astro')
                ->first();

            if (! $astrologer) {

                throw new \Exception('Invalid astrologer.');

            }

            Review::updateOrCreate(

                [
                    'user_id'       => $user->id,
                    'astrologer_id' => $astrologer->id,
                ],

                [
                    'rating' => $request->rating,
                    'review' => $request->review,
                ]

            );

            $stats = Review::where('astrologer_id', $astrologer->id)
                ->selectRaw('COUNT(*) as total_reviews')
                ->selectRaw('AVG(rating) as average_rating')
                ->first();

            $astrologer->rating = round($stats->average_rating, 2);
            $astrologer->rating_count = $stats->total_reviews;
            $astrologer->save();

        }

        $user->load([

            'wallet',
            'reviews.astrologer',

        ]);

        $reviews = $user->reviews
            ->sortByDesc('created_at')
            ->values()
            ->map(function ($review) {

                return [

                    'review_id' => $review->id,

                    'astrologer' => [

                        'id' => optional($review->astrologer)->id,

                        'code' => optional($review->astrologer)->code,

                        'name' => optional($review->astrologer)->name,

                        'profile_image' => optional($review->astrologer)->profile_image
                            ? asset('storage/user/' . $review->astrologer->profile_image)
                            : null,

                        'rating' => (float)(optional($review->astrologer)->rating ?? 0),

                        'rating_count' => (int)(optional($review->astrologer)->rating_count ?? 0),

                    ],

                    'rating' => (int)$review->rating,

                    'review' => $review->review,

                    'created_at' => $review->created_at->format('d M Y h:i A'),

                    'updated_at' => $review->updated_at->format('d M Y h:i A'),

                ];

            });

        DB::commit();

        return response()->json([

            'status' => true,

            'message' => 'Profile updated successfully',

            'user' => [

                'id' => $user->id,

                'code' => $user->code,

                'username' => $user->username,

                'name' => $user->name,

                'email' => $user->email,

                'mobile' => $user->mobile,

                'country_code' => $user->country_code,

                'profile_image' => $user->profile_image
                    ? asset('storage/user/' . $user->profile_image)
                    : null,

                'gender' => $user->gender,

                'dob' => $user->dob,

                'birth_time' => $user->birth_time,

                'birth_place' => $user->birth_place,

                'marital_status' => $user->marital_status,

                'occupation' => $user->occupation,

                'about' => $user->about,

                'address' => $user->address,

                'pincode' => $user->pincode,

                'status' => (int) $user->status,

                'is_online' => (int) $user->is_online,

            ],

            'wallet' => [

                'balance' => (float) ($user->wallet->balance ?? 0),

                'total_added' => (float) ($user->wallet->total_added ?? 0),

                'total_spent' => (float) ($user->wallet->total_spent ?? 0),

                'last_recharge_amount' => (float) ($user->wallet->last_recharge_amount ?? 0),

                'last_recharge_at' => $user->wallet->last_recharge_at,

            ],

            'reviews' => $reviews,

        ]);

        } catch (\Throwable $e) {

            DB::rollBack();

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