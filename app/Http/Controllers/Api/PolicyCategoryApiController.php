<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyCategory;
use Illuminate\Http\Request;

class PolicyCategoryApiController extends Controller
{
    public function index()
    {
        $policyCategory = PolicyCategory::orderBy('id', 'desc')->get();

        return response()->json([
            'status' => true,
            'data'   => $policyCategory,
        ]);
    }

    public function show($id)
    {
        $policyCategory = PolicyCategory::find($id);

        if (! $policyCategory) {
            return response()->json([
                'status'  => false,
                'message' => 'Policy Category not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $policyCategory,
        ]);
    }
}
