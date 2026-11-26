<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Policy;
use Illuminate\Http\Request;

class PolicyApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Policy::with('category');

        if ($request->policy_category_id) {
            $query->where('policy_category_id', $request->policy_category_id);
        }

        $policies = $query->orderBy('date', 'desc')->get();

        $policies->transform(function ($policy) {
            $policy->image_url = $policy->image
                ? asset('storage/policy/' . $policy->image)
                : null;

            return $policy;
        });

        return response()->json([
            'status' => true,
            'data'   => $policies,
        ]);
    }

    public function show($id)
    {
        $policy = Policy::with('category')->find($id);

        if (! $policy) {
            return response()->json([
                'status'  => false,
                'message' => 'Policy not found',
            ], 404);
        }

        $policy->image_url = $policy->image
            ? asset('storage/policy/' . $policy->image)
            : null;

        return response()->json([
            'status' => true,
            'data'   => $policy,
        ]);
    }
}
