<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\CallSession;
use App\Models\ChatSession;
use App\Models\Review;
use App\Models\Country;
use App\Models\State;
use App\Models\City;
use App\Models\PinCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class UserController extends AdminController
{
    public function getIndex()
    {
        return view('admin.users.index');
    }

    public function getList(Request $request)
    {
        $list = User::where("type", "user")
            ->when($request->status !== null && $request->status !== "", fn($q) => $q->where("status", $request->status))
            ->orderByDesc("id");

        return \DataTables::of($list)
            ->addColumn('code_name', function ($row) {
                return '[ <b>'.e($row->code).'</b> ]<br>'.e($row->name);
            })
            ->addColumn("wallet", function ($u) {
                $wallet = $u->wallet->balance ?? 0;
                return "₹ " . number_format($wallet, 2);
            })
            ->rawColumns(["code_name", "wallet"])
            ->make(true);
    }

    public function getCreate(Request $request)
    {

        return view('admin.users.create');
    }

    public function postCreate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "code"     => "required|string|max:255|unique:users,code",
            "name"     => "required|string|max:255",
            "email"    => "required|email|unique:users,email",
            "country_code" => "required|string|max:5",
            "mobile"   => "nullable|digits:10|unique:users,mobile",
            "username" => "required|unique:users,username",
            "password" => "required|min:6|confirmed",

            "dob"         => "nullable|date",
            "birth_time"  => "nullable",
            "birth_place" => "nullable|json",
            "gender"      => "nullable|in:male,female,other",

            "pincode" => "nullable|string|max:10",
            "address" => "nullable|string|max:1000",
            "about"   => "nullable|string|max:2000",

            "country_id" => "nullable|exists:countries,id",
            "state_id"   => "nullable|exists:states,id",
            "city_id"    => "nullable|exists:cities,id",
            "pincode_id" => "nullable|exists:pin_codes,id",

            "profile_image" => "nullable|image|max:4096"
        ]);

        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors()->first()], 422);
        }

        $user = new User;
        $user->type     = "user";
        $user->role_id  = 3;
        $user->code     = $request->code;
        $user->name     = $request->name;
        $user->email    = strtolower($request->email);
        $user->country_code   = $request->country_code;
        $user->mobile   = $request->mobile;
        $user->username = strtolower($request->username);
        $user->password = bcrypt($request->password);

        $user->dob         = $request->dob;
        $user->birth_time  = $request->birth_time;
        $user->birth_place = $request->filled('birth_place')
            ? json_decode($request->birth_place, true)
            : null;
        $user->gender      = $request->gender;

        $user->pincode = $request->pincode;
        $user->address = $request->address;
        $user->about   = $request->about;

        $user->country_id = $request->country_id;
        $user->state_id   = $request->state_id;
        $user->city_id    = $request->city_id;
        $user->pincode_id = $request->pincode_id;

        $user->created_by = auth()->id();
        $user->status     = 1;

        if ($request->hasFile("profile_image")) {
            $user->profile_image = uploadFile("profile_image", 128, 128, "user");
        }

        $user->save();

        // Create wallet for user
        Wallet::create([
            "user_id" => $user->id,
            "balance" => 0,
            "total_earned" => 0
        ]);

        return response()->json(["message" => "User created successfully"]);
    }

    public function getUpdate(Request $request)
    {
        $user = User::with("wallet")->findOrFail($request->id);

        return view('admin.users.update', compact('user'));
    }

    public function postUpdate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "code"     => "required|string|max:255|unique:users,code,{$request->id}",
            "name"     => "required|string|max:255",
            "email"    => "required|email|unique:users,email,{$request->id}",
            "country_code" => "required|string|max:5",
            "mobile"   => "nullable|digits:10|unique:users,mobile,{$request->id}",
            "username" => "required|unique:users,username,{$request->id}",
            "password" => "nullable|min:6|confirmed",

            "dob"         => "nullable|date",
            "birth_time"  => "nullable",
            "birth_place" => "nullable|json",
            "gender"      => "nullable|in:male,female,other",
            
            "pincode"      => "nullable|string|max:10",
            "address" => "nullable|string|max:1000",
            "about"   => "nullable|string|max:2000",

            "country_id" => "nullable|exists:countries,id",
            "state_id"   => "nullable|exists:states,id",
            "city_id"    => "nullable|exists:cities,id",
            "pincode_id" => "nullable|exists:pin_codes,id",

            "profile_image" => "nullable|image|max:4096",

            "balance"      => "nullable|numeric",
            "total_earned" => "nullable|numeric",

            "reviews"      => "nullable|array"
        ]);

        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors()->first()], 422);
        }

        try {

            $user = User::with(["wallet", "reviews"])->findOrFail($request->id);

            // ======================
            // BASIC USER UPDATE
            // ======================
            $user->code       = $request->code;
            $user->name       = $request->name;
            $user->email      = strtolower($request->email);
            $user->country_code     = $request->country_code;
            $user->mobile     = $request->mobile;
            $user->username   = strtolower($request->username);

            if ($request->filled("password")) {
                $user->password = bcrypt($request->password);
            }

            $user->dob         = $request->dob;
            $user->birth_time  = $request->birth_time;
            $user->birth_place = $request->filled('birth_place')
                ? json_decode($request->birth_place, true)
                : null;
            $user->gender      = $request->gender;
            $user->pincode     = $request->pincode;
            $user->address     = $request->address;
            $user->about       = $request->about;

            $user->country_id = $request->country_id;
            $user->state_id   = $request->state_id;
            $user->city_id    = $request->city_id;
            $user->pincode_id = $request->pincode_id;

            $user->modified_by = auth()->id();

            if ($request->hasFile("profile_image")) {
                $user->profile_image = uploadFile("profile_image", 128, 128,"user", $user->profile_image);
            }

            $user->save();


            // ======================
            // WALLET UPDATE
            // ======================
            if (!$user->wallet) {
                $user->wallet()->create([
                    "balance"      => $request->balance ?? 0,
                ]);
            } else {
                $user->wallet->update([
                    "balance"      => $request->balance ?? $user->wallet->balance,
                ]);
            }

            // ======================
            // REVIEWS UPDATE SECTION
            // ======================
            if ($request->has("reviews")) {
                foreach ($request->reviews as $reviewId => $data) {

                    $review = Review::where("id", $reviewId)
                                    ->where("user_id", $user->id)
                                    ->first();

                    if (!$review) continue;

                    $oldAstrologer = $review->astrologer_id;

                    // Delete review
                    if (!empty($data["delete"]) && $data["delete"] == 1) {
                        $review->delete();
                        $this->calculateRating($oldAstrologer);
                        continue;
                    }

                    // Update review
                    $review->update([
                        "astrologer_id" => $data["astrologer_id"] ?? $review->astrologer_id,
                        "rating"        => $data["rating"] ?? $review->rating,
                        "review"        => $data["review"] ?? $review->review,
                    ]);

                    // Recalculate ratings
                    $this->calculateRating($oldAstrologer);
                    $this->calculateRating($review->astrologer_id);
                }
            }

            // ===========================================
            // ADD NEW REVIEW
            // ===========================================
            if (
                $request->filled("new_review_astrologer_id")
                && $request->filled("new_review_rating")
            ) {
                $newReview = Review::create([
                    "user_id"       => $user->id,
                    "astrologer_id" => $request->new_review_astrologer_id,
                    "rating"        => $request->new_review_rating,
                    "review"        => $request->new_review_text,
                ]);

                // Recalculate rating for astrologer
                $this->calculateRating($newReview->astrologer_id);
            }

            return response()->json(["message" => "User updated successfully"]);

        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json([
                "message" => "Something went wrong: " . $e->getMessage()
            ], 500);
        }
    }

    public function getDelete(Request $request)
    {
        $user = User::findOrFail($request->id);

        $user->delete();

        return response()->json(["message" => "User deleted successfully"]);
    }

    public function getChangeStatus(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->status = !$user->status;
        $user->save();

        return response()->json(["message" => "User status updated"]);
    }

    public function getView(Request $request, $id)
    {
        $user = User::with(['wallet', 'reviews.astrologer:id,name,code'])
            ->where('type', 'user')
            ->findOrFail($id);

        /* ================= CALL SUMMARY ================= */
        $callSummary = DB::table('call_sessions')
            ->where('user_id', $id)
            ->where('status', 'completed')
            ->selectRaw('
                COALESCE(SUM(amount),0) as total_amount,
                COALESCE(SUM(duration),0) as total_duration
            ')
            ->first();

        /* ================= CHAT SUMMARY ================= */
        $chatSummary = DB::table('chat_sessions')
            ->where('user_id', $id)
            ->where('status', 'completed')
            ->selectRaw('
                COALESCE(SUM(amount),0) as total_amount,
                COALESCE(SUM(duration),0) as total_duration
            ')
            ->first();

        /* ================= INTERACTION HISTORY ================= */
        $chatHistory = DB::table('chat_sessions as cs')
            ->join('users as a', 'a.id', '=', 'cs.astrologer_id')
            ->where('cs.user_id', $id)
            ->select([
                DB::raw("'CHAT' as type"),
                'a.code as astrologer_code',
                'a.name as astrologer_name',
                'cs.started_at',
                'cs.ended_at',
                'cs.duration',
                'cs.amount',
                'cs.status',
            ]);

        $callHistory = DB::table('call_sessions as cls')
            ->join('users as a', 'a.id', '=', 'cls.astrologer_id')
            ->where('cls.user_id', $id)
            ->select([
                DB::raw("'CALL' as type"),
                'a.code as astrologer_code',
                'a.name as astrologer_name',
                'cls.started_at',
                'cls.ended_at',
                'cls.duration',
                'cls.amount',
                'cls.status',
            ]);

        $interactionHistory = $chatHistory
            ->unionAll($callHistory)
            ->orderByDesc('started_at')
            ->get();

        /* ================= RECHARGE HISTORY ================= */
        $rechargeHistory = DB::table('wallet_recharges')
            ->where('wallet_id', optional($user->wallet)->id)
            ->orderByDesc('recharged_at')
            ->get();

        /* ================= PROFILE IMAGE ================= */
        $user->profile_image_url = $user->profile_image
            ? asset("storage/user/{$user->profile_image}")
            : asset('default-user.png');

        /* ================= REVIEWS GIVEN BY USER ================= */
        $latest_reviews = Review::where('user_id', $id)
            ->with('astrologer:id,name,code')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.users.view', compact(
            'user',
            'callSummary',
            'chatSummary',
            'interactionHistory',
            'rechargeHistory',
            'latest_reviews'
        ));
    }

    public function getWallet($id)
    {
        $wallet = Wallet::where("user_id", $id)->first();
        $tx = WalletTransaction::where("wallet_id", $wallet->id)->orderByDesc("id")->get();

        return response()->json([
            "wallet" => $wallet,
            "transactions" => $tx
        ]);
    }

    public function postWalletUpdate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "user_id" => "required|exists:users,id",
            "type"    => "required|in:credit,debit",
            "amount"  => "required|numeric|min:1",
            "description" => "nullable|string|max:500"
        ]);

        if ($validator->fails()) {
            return response()->json(["message" => $validator->errors()->first()], 422);
        }

        $wallet = Wallet::where("user_id", $request->user_id)->firstOrFail();

        $before = $wallet->balance;

        if ($request->type === "credit") {

            // Increase balance
            $after = $before + $request->amount;

            // Store ONLY the last recharge amount
            $wallet->last_recharge_amount = $request->amount;
            $wallet->last_recharge_at = now();
        }
        else {

            if ($before < $request->amount) {
                return response()->json(["message" => "Insufficient balance"], 422);
            }

            // Decrease balance
            $after = $before - $request->amount;

            // DO NOT change last_recharge_amount here
        }

        $wallet->balance = $after;
        $wallet->save();

        WalletTransaction::create([
            "wallet_id"   => $wallet->id,
            "type"        => $request->type,
            "amount"      => $request->amount,
            "balance_before" => $before,
            "balance_after"  => $after,
            "description" => $request->description,
        ]);

        return response()->json(["message" => "Wallet updated successfully"]);
    }

    public function getReviews($id)
    {
        $reviews = Review::with("astrologer:id,name")
            ->where("user_id", $id)
            ->orderByDesc("id")
            ->get();

        return response()->json(["reviews" => $reviews]);
    }

    private function calculateRating($astrologerId)
    {
        $reviews = Review::where("astrologer_id", $astrologerId)->get();

        $count = $reviews->count();
        $avg   = $count ? round($reviews->avg("rating"), 2) : 0;

        User::where("id", $astrologerId)->update([
            "rating"       => $avg,
            "rating_count" => $count
        ]);
    }
}