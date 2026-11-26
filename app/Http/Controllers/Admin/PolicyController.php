<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Models\Policy;
use App\Models\PolicyCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PolicyController extends AdminController
{
    public function getIndex(Request $request)
    {
        return view('admin.policies.index');
    }

    public function getList(Request $request)
    {
        $list = Policy::select('policies.*')
            ->with('category')
            ->when($request->policy_category_id, function ($q) use ($request) {
                $q->where('policies.policy_category_id', $request->policy_category_id);
            })
            ->when($request->policy_id, function ($q) use ($request) {
                $q->where('policies.id', $request->policy_id);
            })
            ->orderBy('policies.id', 'desc');

        return \DataTables::of($list)
            ->addColumn('category_name', function ($row) {
                return $row->category?->name ?? '-';
            })
            ->make();
    }

    public function getCreate()
    {
        $categories = PolicyCategory::orderBy('name')->get();
        return view('admin.policies.create', compact('categories'));
    }

    public function postCreate(Request $request)
    {
        $request->validate([
            'policy_category_id' => 'required|exists:policy_categories,id',
            'name'             => 'required|string|max:255',
            'slug'             => 'nullable|unique:policies,slug',
            'image'    => 'nullable|image',
            'date'             => 'nullable|date',
            'description'      => 'nullable|string',
        ]);

        try {
            $policy = new Policy();
            $policy->policy_category_id = $request->policy_category_id;
            $policy->name = $request->name;
            $policy->slug = Str::slug($request->slug ?? $request->name);
            $policy->date = $request->date;
            $policy->description = $request->description;

            if ($request->hasFile('image')) {
                $policy->image = uploadFile('image', null, null, 'policy');
            }

            $policy->save();

            return response()->json([
                'message' => 'Policy created successfully'
            ]);
        } catch (\Throwable $e) {
            \Log::error($e);

            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function getUpdate(Request $request)
    {
        $policy = Policy::findOrFail($request->id);
        $categories = PolicyCategory::orderBy('name')->get();

        return view('admin.policies.update', compact('policy', 'categories'));
    }

    public function postUpdate(Request $request, $id)
    {
        $policy = Policy::findOrFail($id);

        $request->validate([
            'policy_category_id' => 'required|exists:policy_categories,id',
            'name'             => 'required|string|max:255',
            'slug'             => 'required|unique:policies,slug,' . $policy->id,
            'image'    => 'nullable|image',
            'date'             => 'nullable|date',
            'description'      => 'nullable|string',
        ]);

        try {
            $policy->policy_category_id = $request->policy_category_id;
            $policy->name = $request->name;
            $policy->slug = Str::slug($request->slug);
            $policy->date = $request->date;
            $policy->description = $request->description;

            if ($request->hasFile('image')) {
                $policy->image = uploadFile('image', null, null, 'policy', $policy->image);
            }

            $policy->save();

            return response()->json([
                'message' => 'Policy updated successfully'
            ]);
        } catch (\Throwable $e) {
            \Log::error($e);

            return response()->json([
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    public function getDelete(Request $request)
    {
        $policy = Policy::findOrFail($request->id);
        $policy->delete();

        return response()->json([
            'message' => 'Policy deleted successfully'
        ]);
    }
}
