<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Models\City;
use App\Models\Country;
use App\Models\PinCode;
use App\Models\State;
use App\Models\User;
use App\Models\CallSession;
use App\Models\ChatSession;
use App\Models\WalletTransaction;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AstroController extends AdminController
{
    public function getIndex(Request $request)
    {

        return view('admin.astrologers.index');
    }

    public function getList(Request $request)
    {
        $list = \App\Models\User::where('role_id', 2)
            ->when($request->astrologer_id, function ($q) use ($request) {
                $q->where('id', $request->astrologer_id);
            })
            ->when($request->status !== null && $request->status !== '', function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderByDesc('updated_at');

        return \DataTables::of($list)
            ->addColumn('code_name', function ($row) {
                return '[ <b>'.e($row->code).'</b> ]<br>'.e($row->name);
            })
            ->rawColumns(['code_name'])
            ->make();
    }

    public function getCreate(Request $request)
    {
        return view('admin.astrologers.create');
    }

    public function postCreate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'code' => 'required|unique:users,code',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            "country_code" => "required|string|max:5",
            'mobile' => 'nullable|digits:10|unique:users,mobile',
            'username' => 'required|unique:users,username',
            'password' => 'required|min:6|confirmed',

            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'date_of_joining' => 'nullable|date',

            'experience' => 'required|integer|min:0',
            'daily_available_hours' => 'required|integer|min:1|max:24',
            'astro_education' => 'required|array|min:1',
            'expertise' => 'required|array|min:1',
            'category' => 'required|array|min:1',
            'languages' => 'required|array|min:1',

            'chat_price' => 'required|numeric|min:0',
            'call_price' => 'required|numeric|min:0',

            'about' => 'nullable|string|max:2000',
            "pincode" => "nullable|string|max:10",
            'address' => 'nullable|string|max:2000',

            'city_id' => 'nullable|exists:cities,id',
            'state_id' => 'nullable|exists:states,id',
            'country_id' => 'nullable|exists:countries,id',
            'pincode_id' => 'nullable|exists:pin_codes,id',

            'is_family_astrologer' => 'required|in:0,1',
            'family_astrology_details' => 'nullable|string|max:1000',

            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'status' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $astro = new \App\Models\User;

            $astro->type = 'astro';
            $astro->role_id = 2;

            $astro->code = $request->code;
            $astro->name = $request->name;
            $astro->email = strtolower($request->email);
            $astro->country_code = $request->country_code;
            $astro->mobile = $request->mobile;

            $astro->username = strtolower($request->username);
            $astro->password = bcrypt($request->password);

            $astro->gender = $request->gender;
            $astro->dob = $request->dob;
            $astro->date_of_joining = $request->date_of_joining;

            $astro->experience = $request->experience;
            $astro->daily_available_hours = $request->daily_available_hours;

            // ✅ CLEAN JSON ARRAY SAVE
            $astro->astro_education = array_values($request->astro_education);
            $astro->expertise = array_values($request->expertise);
            $astro->category = array_values($request->category);
            $astro->languages = array_values($request->languages);

            $astro->chat_price = $request->chat_price;
            $astro->call_price = $request->call_price;

            $astro->about = $request->about;
            $astro->pincode = $request->pincode;
            $astro->address = $request->address;

            $astro->city_id = $request->city_id;
            $astro->state_id = $request->state_id;
            $astro->country_id = $request->country_id;
            $astro->pincode_id = $request->pincode_id;

            $astro->is_family_astrologer = $request->is_family_astrologer;
            $astro->family_astrology_details = $request->family_astrology_details;

            $astro->status = $request->status ?? 1;
            $astro->created_by = auth()->id();

            if ($request->hasFile('profile_image')) {
                $astro->profile_image = uploadFile('profile_image', 128, 128, 'user'
                );
            }

            $astro->save();

            return response()->json([
                'message' => 'Astrologer created successfully',
            ]);

        } catch (\Throwable $th) {
            \Log::error($th);

            return response()->json([
                'message' => 'Failed to process your request',
            ], 500);
        }
    }

    public function getUpdate(Request $request)
    {
        $astro = User::with([
            "reviews" => function($q){
                $q->with(['user:id,name']);
            }
        ])->findOrFail($request->id);

        return view('admin.astrologers.update', compact('astro'));
    }

    public function postUpdate(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'code' => "required|unique:users,code,{$request->id}",
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$request->id}",
            'country_code' => "required|string|max:5",
            'mobile' => "nullable|digits:10|unique:users,mobile,{$request->id}",
            'username' => "required|unique:users,username,{$request->id}",
            'password' => 'nullable|min:6|confirmed',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'date_of_joining' => 'nullable|date',
            'experience' => 'required|integer|min:0',
            'daily_available_hours' => 'required|integer|min:1|max:24',
            'astro_education' => 'required|array|min:1',
            'expertise' => 'required|array|min:1',
            'category' => 'required|array|min:1',
            'languages' => 'required|array|min:1',
            'chat_price' => 'required|numeric|min:0',
            'call_price' => 'required|numeric|min:0',
            'about' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:2000',
            'pincode' => "nullable|string|max:10",
            'city_id' => 'nullable|exists:cities,id',
            'state_id' => 'nullable|exists:states,id',
            'country_id' => 'nullable|exists:countries,id',
            'pincode_id' => 'nullable|exists:pin_codes,id',
            'is_family_astrologer' => 'required|in:0,1',
            'family_astrology_details' => 'nullable|string|max:1000',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'status' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
            ], 422);
        }

        try {
            $astro = \App\Models\User::findOrFail($request->id);
            $astro->type = 'astro';
            $astro->role_id = 2;
            $astro->code = $request->code;
            $astro->name = $request->name;
            $astro->email = strtolower($request->email);
            $astro->country_code = $request->country_code;
            $astro->mobile = $request->mobile;
            $astro->username = strtolower($request->username);
            if ($request->filled('password')) {
                $astro->password = bcrypt($request->password);
            }
            $astro->gender = $request->gender;
            $astro->dob = $request->dob;
            $astro->date_of_joining = $request->date_of_joining;
            $astro->experience = $request->experience;
            $astro->daily_available_hours = $request->daily_available_hours;
            $astro->astro_education = array_values($request->astro_education);
            $astro->expertise = array_values($request->expertise);
            $astro->category = array_values($request->category);
            $astro->languages = array_values($request->languages);
            $astro->chat_price = $request->chat_price;
            $astro->call_price = $request->call_price;
            $astro->is_family_astrologer = $request->is_family_astrologer;
            $astro->family_astrology_details = $request->family_astrology_details;
            $astro->about = $request->about;
            $astro->address = $request->address;
            $astro->pincode = $request->pincode;
            $astro->country_id = $request->country_id;
            $astro->state_id = $request->state_id;
            $astro->city_id = $request->city_id;
            $astro->pincode_id = $request->pincode_id;
            $astro->status = $request->status ?? 1;
            $astro->modified_by = auth()->id();

            if ($request->hasFile('profile_image')) {
                $astro->profile_image = uploadFile('profile_image', 128, 128, 'user', $astro->profile_image
                );
            }

            $astro->save();

            return response()->json([
                'message' => 'Astrologer updated successfully',
            ]);

        } catch (\Throwable $th) {
            \Log::error($th);

            return response()->json([
                'message' => 'Failed to process your request',
            ], 500);
        }
    }

    public function getDelete(Request $request)
    {
        $astro = \App\Models\User::findOrFail($request->id);

        $astro->delete();

        return response()->json(['message' => 'Your request processed successfully.']);
    }

    public function getChangeStatus(Request $request)
    {
        $astro = \App\Models\User::findOrFail($request->id);
        if (! blank($astro)) {
            $astro->status = (int) ! $astro->status;
            $astro->save();
        }

        return response()->json(['message' => 'Your request processed successfully.']);
    }

    public function getView(Request $request, $id)
    {
        $astro = User::where('type', 'astro')
            ->with([
                'wallet',
                'reviews.user:id,name,code',
            ])
            ->findOrFail($id);

        /* ================= CALL SESSION HISTORY (DAY BY DAY – SESSION WISE) ================= */
        $call_history = CallSession::with('user:id,name,code')
            ->where('astrologer_id', $id)
            ->orderBy('started_at', 'desc')
            ->get();

        /* ================= CHAT SESSION HISTORY (DAY BY DAY – SESSION WISE) ================= */
        $chat_history = ChatSession::with('user:id,name,code')
            ->where('astrologer_id', $id)
            ->orderBy('started_at', 'desc')
            ->get();

        /* ================= LATEST REVIEWS ================= */
        $latest_reviews = Review::where('astrologer_id', $id)
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();

        $astro->profile_image_url = $astro->profile_image
            ? asset("storage/user/{$astro->profile_image}")
            : 'https://placehold.co/300x300';

        $total_call_earnings = CallSession::where('astrologer_id', $id)
            ->where('status', 'completed')
            ->sum('amount');

        $total_call_minutes = CallSession::where('astrologer_id', $id)
            ->where('status', 'completed')
            ->sum('duration');
        
        $total_chat_earnings = ChatSession::where('astrologer_id', $id)
            ->where('status', 'completed')
            ->sum('amount');

        $total_chat_minutes = ChatSession::where('astrologer_id', $id)
            ->where('status', 'completed')
            ->sum('duration');

        return view(
            'admin.astrologers.view',
            compact('astro', 'call_history', 'chat_history', 'latest_reviews', 'total_call_earnings', 'total_call_minutes', 'total_chat_earnings', 'total_chat_minutes')
        );
    }

    public function filterEarnings(Request $request)
    {
        $request->validate([
            'id'         => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ]);

        $astroId = $request->id;

        /* ================= CALL SESSIONS (SESSION WISE) ================= */
        $call_history = CallSession::with('user:id,name,code')
            ->where('astrologer_id', $astroId)
            ->whereBetween(DB::raw('DATE(started_at)'), [
                $request->start_date,
                $request->end_date
            ])
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'user_code' => $c->user->code,
                    'user_name' => $c->user->name,
                    'started_at'=> $c->started_at,
                    'ended_at'  => $c->ended_at,
                    'duration'  => $c->duration,
                    'amount'    => number_format($c->amount, 2),
                    'status'    => ucfirst($c->status),
                ];
            });

        /* ================= CHAT SESSIONS (SESSION WISE) ================= */
        $chat_history = ChatSession::with('user:id,name,code')
            ->where('astrologer_id', $astroId)
            ->whereBetween(DB::raw('DATE(started_at)'), [
                $request->start_date,
                $request->end_date
            ])
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'user_code' => $c->user->code,
                    'user_name' => $c->user->name,
                    'started_at'=> $c->started_at,
                    'ended_at'  => $c->ended_at,
                    'duration'  => $c->duration,
                    'amount'    => number_format($c->amount, 2),
                    'status'    => ucfirst($c->status),
                ];
            });

        return response()->json([
            'call_history' => $call_history,
            'chat_history' => $chat_history,
        ]);
    }

}