<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminController;
use App\Models\PolicyCategory;
use Illuminate\Http\Request;

class PolicyCategoryController extends AdminController
{
    public function getIndex(Request $request)
    {
        return view('admin.policy_categories.index');
    }

    public function getList(Request $request)
    {
        $list = PolicyCategory::query()
            ->when($request->id, function ($q) use ($request) {
                $q->where('id', $request->id);
            })
            ->when($request->slug, function ($q) use ($request) {
                $q->where('slug', $request->slug);
            })
            ->orderBy('id', 'desc');

        return \DataTables::of($list)->make();
    }

    public function getCreate()
    {
        return view('admin.policy_categories.create');
    }

    public function postCreate(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:policy_categories,name',
            'slug' => 'nullable|unique:policy_categories,slug',
        ]);

        try {

            PolicyCategory::create([
                'name' => $request->name,
                'slug' => \Str::slug($request->slug ?? $request->name),
            ]);

            return response()->json(['message' => 'Policy category created successfully']);
        } catch (\Throwable $e) {
            \Log::error($e);

            return response()->json(['message' => 'Something went wrong'], 500);
        }
    }

    public function getUpdate(Request $request)
    {
        $policyCategory = PolicyCategory::findOrFail($request->id);

        return view('admin.policy_categories.update', compact('policyCategory'));
    }

    public function postUpdate(Request $request, $id)
    {
        $policyCategory = PolicyCategory::findOrFail($id);

        $request->validate([
            'name' => 'required|unique:policy_categories,name,'.$policyCategory->id,
            'slug' => 'required|unique:policy_categories,slug,'.$policyCategory->id,
        ]);

        $policyCategory->update([
            'name' => $request->name,
            'slug' => \Str::slug($request->slug),
        ]);

        return response()->json(['message' => 'Policy category updated successfully']);
    }

    public function getDelete(Request $request)
    {
        $policyCategory = \App\Models\PolicyCategory::findOrFail($request->id);

        $policyCategory->delete();

        return response()->json(['message' => 'Your request processed successfully.']);
    }

    public function getChangeStatus(Request $request)
    {
        $policyCategory = \App\Models\PolicyCategory::findOrFail($request->id);
        if (! blank($policyCategory)) {
            $policyCategory->status = (int) ! $policyCategory->status;
            $policyCategory->save();
        }

        return response()->json(['message' => 'Your request processed successfully.']);
    }
}
