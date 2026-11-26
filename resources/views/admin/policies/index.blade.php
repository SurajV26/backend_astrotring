@extends('layouts.master')

@section('title') Policies @endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">All Policies</h4>

            @if (Can::is_accessible('policies', 'create'))
                <div class="page-title-right">
                    <a href="{{ route('admin.policies.create.index') }}" class="btn btn-soft-info">
                        <i class="fas fa-plus"></i> Create
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- FILTER SECTION --}}
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Filter</h4>
                <button id="reset-filter-btn" type="button" class="btn btn-light">
                    <i class="fa fa-undo"></i> Reset
                </button>
            </div>
            <div class="card-body">
                <div class="row">

                    <div class="col">
                        <label class="form-label fw-bold">Policy Category</label>
                        <select id="policy_category_id"
                                class="form-control select2-class2"
                                data-placeholder="Select Category">
                            <option value=""></option>
                            @foreach(\App\Models\PolicyCategory::orderBy('name')->get() as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col">
                        <label class="form-label fw-bold">Policy</label>
                        <select id="policy_id"
                                class="form-control select2-class2"
                                data-placeholder="Select Policy">
                            <option value=""></option>
                            @foreach(\App\Models\Policy::orderBy('name')->get() as $policy)
                                <option value="{{ $policy->id }}">
                                    {{ $policy->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border">
            <div class="card-body">
                <table id="data-table" class="table table-bordered dt-responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Policy Name</th>
                            <th>Slug</th>
                            <th width="120">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
$(function () {

    const reloadTable = () => {
        $('#data-table').DataTable().ajax.reload();
    };

    $('#data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('admin.policies.list') }}",
            data: function (d) {
                d.policy_category_id = $('#policy_category_id').val();
                d.policy_id = $('#policy_id').val();
            }
        },
        columns: [
            { data: 'category_name', name: 'category.name' },
            { data: 'name', name: 'policies.name' },  
            { data: 'slug', name: 'policies.slug' },   
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data, type, row) {
                    return `
                        @if (Can::is_accessible('policies', 'update'))
                            <a href="{{ route('admin.policies.update.index') }}/${row.id}"
                               class="btn btn-soft-info btn-sm waves-effect waves-light">
                                <i class="bx bx-pencil font-size-16"></i>
                            </a>
                        @endif

                        @if (Can::is_accessible('policies', 'delete'))
                            <button type="button"
                                    class="btn btn-soft-danger btn-sm waves-effect waves-light delete-entry"
                                    data-href="{{ route('admin.policies.delete') }}/${row.id}">
                                <i class="bx bx-trash font-size-16"></i>
                            </button>
                        @endif
                    `;
                }
            }
        ]
    });

    $('#policy_category_id, #policy_id').on('change', function () {
        reloadTable();
    });

    $('#reset-filter-btn').on('click', function () {
        $('#policy_category_id').val('').trigger('change');
        $('#policy_id').val('').trigger('change');
        reloadTable();
    });

});
</script>
@endsection
