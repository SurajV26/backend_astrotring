<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;

use App\Models\User;
use App\Models\Wallet;
use App\Models\CallSession;
use App\Models\ChatSession;
use App\Models\PayoutRequest;
use App\Models\Review;

class AstrologerApiController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'country_code' => 'required|string|max:5',
            'mobile' => 'required|digits:10|unique:users,mobile',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:6',

            'experience' => 'required|integer|min:0',
            'daily_available_hours' => 'required|integer|min:1|max:24',

            'astro_education' => 'required|array|min:1',
            'expertise' => 'required|array|min:1',
            'category' => 'required|array|min:1',
            'languages' => 'required|array|min:1',

            'is_family_astrologer' => 'required|in:0,1',
            'family_astrology_details' => 'nullable|string|max:1000',
            
            'profile_image' => 'nullable|string', 

            'terms_accepted' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $astro = User::create([
            'type' => 'astro',
            'role_id' => 2,
            'code' => $this->generateAstroCode($request->name),
            'terms_accepted' => $request->terms_accepted,

            'name' => $request->name,
            'email' => strtolower($request->email),
            'country_code' => $request->country_code,
            'mobile' => $request->mobile,
            'username' => strtolower($request->username),
            'password' => bcrypt($request->password),

            'experience' => $request->experience,
            'daily_available_hours' => $request->daily_available_hours,

            'astro_education' => array_values($request->astro_education),
            'expertise' => array_values($request->expertise),
            'category' => array_values($request->category),
            'languages' => array_values($request->languages),

            'is_family_astrologer' => $request->is_family_astrologer,
            'family_astrology_details' => $request->family_astrology_details,

            // defaults
            'chat_price' => 0,
            'call_price' => 0,
            'status' => 0,
        ]);

        if ($request->filled('profile_image')) {
            $astro->profile_image = $this->saveBase64Image(
                $request->profile_image,
                'user'
            );
            $astro->save();
        }

        Wallet::create([
            'user_id' => $astro->id,
            'balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Astrologer registered successfully',
            'token' => $astro->createToken('astro_token')->plainTextToken,
            'astro' => $astro->load('wallet'),
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $astro = User::where('type', 'astro')
            ->where('username', $request->username)
            ->first();

        if (! $astro || ! Hash::check($request->password, $astro->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid username or password',
            ], 401);
        }

        if (! $astro->status) {
            return response()->json([
                'status' => false,
                'message' => 'Your account is inactive',
            ], 403);
        }

        $astro->update([
            'is_online' => 1,
            'last_seen_at' => now(),
        ]);

        $astro->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $astro->createToken('astro_token')->plainTextToken,
            'astro' => $astro,
        ]);
    }

    public function list()
    {
        $astros = User::where('role_id', 2)
            ->where('status', 1)
            ->select([
                'id','code','name','username','profile_image',
                'experience','daily_available_hours',
                'rating','rating_count','is_online',
                'languages','expertise','category',
                'chat_price','call_price'
            ])
            ->orderByDesc('rating')
            ->get()
            ->map(function ($astro) {
                return [
                    'id' => $astro->id,
                    'code' => $astro->code,
                    'name' => $astro->name,
                    'username' => $astro->username,
                    'experience' => (int) $astro->experience,
                    'daily_available_hours' => (int) $astro->daily_available_hours,
                    'rating' => (float) $astro->rating,
                    'rating_count' => (int) $astro->rating_count,
                    'is_online' => (bool) $astro->is_online,
                    'chat_price' => (float) $astro->chat_price,
                    'call_price' => (float) $astro->call_price,
                    'languages' => $astro->languages,
                    'expertise' => $astro->expertise,
                    'category' => $astro->category,
                    'profile_image' => $astro->profile_image
                        ? asset('storage/user/'.$astro->profile_image)
                        : null,
                ];
            });

        return response()->json([
            'status' => true,
            'count' => $astros->count(),
            'data' => $astros,
        ]);
    }

    public function show($id)
    {
        $astro = User::where('id', $id)
            ->where('type', 'astro')
            ->where('status', 1)
            ->first();

        if (!$astro) {
            return response()->json([
                'status' => false,
                'message' => 'Astrologer not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $astro->id,
                'code' => $astro->code,
                'name' => $astro->name,
                'username' => $astro->username,
                'experience' => (int) $astro->experience,
                'daily_available_hours' => (int) $astro->daily_available_hours,
                'rating' => (float) $astro->rating,
                'rating_count' => (int) $astro->rating_count,
                'is_online' => (bool) $astro->is_online,
                'chat_price' => (float) $astro->chat_price,
                'call_price' => (float) $astro->call_price,
                'astro_education' => $astro->astro_education,
                'languages' => $astro->languages,
                'expertise' => $astro->expertise,
                'category' => $astro->category,
                'profile_image' => $astro->profile_image
                    ? asset('storage/user/'.$astro->profile_image)
                    : null,
            ]
        ]);
    }

    public function profile(Request $request)
    {
        $astro = auth()->user()->load('wallet');

        $perPage = (int) $request->get('per_page', 20);

        /* ================= TODAY SUMMARY ================= */
        $todayCall = CallSession::where('astrologer_id', $astro->id)
            ->where('status', 'completed')
            ->whereDate('started_at', today())
            ->sum('amount');

        $todayChat = ChatSession::where('astrologer_id', $astro->id)
            ->where('status', 'completed')
            ->whereDate('started_at', today())
            ->sum('amount');

        $todayWithdraw = PayoutRequest::where('user_id', $astro->id)
            ->where('status', 'approved')
            ->whereDate('updated_at', today())
            ->sum('amount');

        /* ================= LIFETIME SUMMARY ================= */
        $totalCall = CallSession::where('astrologer_id', $astro->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalChat = ChatSession::where('astrologer_id', $astro->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalWithdraw = PayoutRequest::where('user_id', $astro->id)
            ->where('status', 'approved')
            ->sum('amount');

        /* ================= REVIEWS (PAGINATED) ================= */
        $reviewsPaginator = Review::where('astrologer_id', $astro->id)
            ->with('user:id,name,code')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'reviews_page');

        $reviewsPaginator->getCollection()->transform(function ($r) {
            return [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'review' => $r->review,
                'user_name' => $r->user->name ?? null,
                'user_code' => $r->user->code ?? null,
                'date' => $r->created_at,
            ];
        });

        /* ================= RATING BREAKDOWN ================= */
        $ratingRaw = Review::where('astrologer_id', $astro->id)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $ratingBreakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingBreakdown[$i] = (int) ($ratingRaw[$i] ?? 0);
        }

        /* ================= CALL HISTORY (PAGINATED) ================= */
        $callPaginator = CallSession::where('astrologer_id', $astro->id)
            ->with('user:id,name,code')
            ->orderByDesc('started_at')
            ->paginate($perPage, ['*'], 'call_page');

        $callPaginator->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'user_name' => $c->user->name ?? null,
                'user_code' => $c->user->code ?? null,
                'started_at' => $c->started_at,
                'ended_at' => $c->ended_at,
                'duration_minutes' => $c->duration,
                'amount' => (float) $c->amount,
                'status' => $c->status,
            ];
        });

        /* ================= CHAT HISTORY (PAGINATED) ================= */
        $chatPaginator = ChatSession::where('astrologer_id', $astro->id)
            ->with('user:id,name,code')
            ->orderByDesc('started_at')
            ->paginate($perPage, ['*'], 'chat_page');

        $chatPaginator->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'user_name' => $c->user->name ?? null,
                'user_code' => $c->user->code ?? null,
                'started_at' => $c->started_at,
                'ended_at' => $c->ended_at,
                'duration_minutes' => $c->duration,
                'amount' => (float) $c->amount,
                'status' => $c->status,
            ];
        });

        /* ================= WITHDRAW HISTORY (PAGINATED) ================= */
        $withdrawPaginator = PayoutRequest::where('user_id', $astro->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'withdraw_page');

        $withdrawPaginator->getCollection()->transform(function ($p) {
            return [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'requested_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ];
        });

        return response()->json([
            'status' => true,

            'astro' => [
                'id' => $astro->id,
                'code' => $astro->code,
                'username' => $astro->username,
                'name' => $astro->name,
                'gender' => $astro->gender,
                'dob' => $astro->dob,
                'birth_time' => $astro->birth_time,
                'birth_place' => $astro->birth_place,
                'email' => $astro->email,
                'country_code' => $astro->country_code,
                'mobile' => $astro->mobile,
                'address' => $astro->address,
                'pincode' => $astro->pincode,
                'experience' => $astro->experience,
                'languages' => $astro->languages,
                'category' => $astro->category,
                'expertise' => $astro->expertise,
                'chat_price' => $astro->chat_price,
                'call_price' => $astro->call_price,
                'daily_available_hours' => $astro->daily_available_hours,
                'is_family_astrologer' => (bool) $astro->is_family_astrologer,
                'family_astrology_details' => $astro->family_astrology_details,
                'astro_education' => $astro->astro_education,
                'about' => $astro->about,
                'rating' => $astro->rating,
                'rating_count' => $astro->rating_count,
                'is_online' => (bool) $astro->is_online,
                'last_seen_at' => $astro->last_seen_at,
                'profile_image' => $astro->profile_image
                    ? asset('storage/user/'.$astro->profile_image)
                    : null,
            ],

            'wallet' => [
                'balance' => (float) ($astro->wallet?->balance ?? 0),
                'total_earned' => (float) ($astro->wallet?->total_earned ?? 0),
                'total_withdrawn' => (float) ($astro->wallet?->total_withdrawn ?? 0),
            ],

            'today_summary' => [
                'call_earnings' => (float) $todayCall,
                'chat_earnings' => (float) $todayChat,
                'total_earnings' => (float) ($todayCall + $todayChat),
                'withdrawn' => (float) $todayWithdraw,
            ],

            'lifetime_summary' => [
                'call_earnings' => (float) $totalCall,
                'chat_earnings' => (float) $totalChat,
                'total_earnings' => (float) ($totalCall + $totalChat),
                'total_withdrawn' => (float) $totalWithdraw,
            ],

            'rating_breakdown' => $ratingBreakdown,

            'reviews' => [
                'data' => $reviewsPaginator->items(),
                'meta' => [
                    'current_page' => $reviewsPaginator->currentPage(),
                    'last_page' => $reviewsPaginator->lastPage(),
                    'per_page' => $reviewsPaginator->perPage(),
                    'total' => $reviewsPaginator->total(),
                ],
            ],

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

            'withdraw_history' => [
                'data' => $withdrawPaginator->items(),
                'meta' => [
                    'current_page' => $withdrawPaginator->currentPage(),
                    'last_page' => $withdrawPaginator->lastPage(),
                    'per_page' => $withdrawPaginator->perPage(),
                    'total' => $withdrawPaginator->total(),
                ],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $astro = auth()->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $astro->id,
            'country_code' => 'nullable|string|max:5',
            'mobile' => 'nullable|digits:10|unique:users,mobile,' . $astro->id,

            'gender' => 'nullable|in:male,female,other',
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

            'experience' => 'nullable|integer|min:0',
            'daily_available_hours' => 'nullable|integer|min:1|max:24',

            'astro_education' => 'nullable|array|min:1',
            'expertise' => 'nullable|array|min:1',
            'category' => 'nullable|array|min:1',
            'languages' => 'nullable|array|min:1',

            'about' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:2000',
            'pincode' => 'nullable|string|max:10',

            'is_family_astrologer' => 'nullable|in:0,1',
            'family_astrology_details' => 'nullable|string|max:1000',

            'profile_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->filled('profile_image')) {
            $astro->profile_image = $this->saveBase64Image(
                $request->profile_image,
                'user',
                $astro->profile_image
            );
        }

        $astro->update(array_filter([
            'name' => $request->name,
            'email' => $request->email,
            'country_code' => $request->country_code,
            'mobile' => $request->mobile,
            'gender' => $request->gender,
            'dob' => $request->dob,
            'birth_time' => $request->birth_time,
            'birth_place' => $request->birth_place,
            'experience' => $request->experience,
            'daily_available_hours' => $request->daily_available_hours,
            'astro_education' => $request->astro_education ? array_values($request->astro_education) : null,
            'expertise' => $request->expertise ? array_values($request->expertise) : null,
            'category' => $request->category ? array_values($request->category) : null,
            'languages' => $request->languages ? array_values($request->languages) : null,
            'about' => $request->about,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'is_family_astrologer' => $request->is_family_astrologer,
            'family_astrology_details' => $request->family_astrology_details,
            'modified_by' => $astro->id,
        ], fn ($v) => !is_null($v)));

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'astro' => $astro->fresh(),
        ]);
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

        $astro = auth()->user();

        if (! Hash::check($request->old_password, $astro->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Old password does not match',
            ], 401);
        }

        $astro->update([
            'password' => bcrypt($request->new_password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    public function logout(Request $request)
    {
        $astro = auth()->user();
        $astro->update(['is_online' => 0, 'last_seen_at' => now()]);
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function delete()
    {
        $astro = auth()->user();
        $astro->tokens()->delete();
        $astro->delete();

        return response()->json([
            'status' => true,
            'message' => 'Astrologer deleted successfully',
        ]);
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

    private function generateAstroCode($name)
    {
        $prefix = strtoupper(substr(explode(' ', $name)[0], 0, 3));
        $last = User::where('code', 'like', $prefix.'%')->latest()->first();
        $num = $last && preg_match('/'.$prefix.'(\d+)/', $last->code, $m)
            ? (int)$m[1] + 1
            : 1;

        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
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
}
