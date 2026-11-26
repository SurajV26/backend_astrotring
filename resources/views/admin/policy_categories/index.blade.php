@extends('layouts.master')

@section('title')
    Policy Categories
@endsection

@section('content')
    {{-- PAGE HEADER --}}
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Policy Categories</h4>

                <div class="page-title-right">
                    <a href="javascript:void(0);" data-href="{{ route('admin.policy_categories.create.index') }}"
                        class="btn btn-soft-info open-remote-modal">
                        <i class="fas fa-plus"></i> Create
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTER --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Filter</h4>
                    <button id="reset-filter" class="btn btn-light">
                        <i class="fa fa-undo"></i> Reset
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Name</label>
                            <select id="filter_name" class="form-control select2-class2"
                                data-placeholder="Select Policy Name">
                                <option value=""></option>
                                @foreach (\App\Models\PolicyCategory::orderBy('name')->get() as $category)
                                    <option value="{{ $category->id }}">
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Slug</label>
                            <select id="filter_slug" class="form-control select2-class2"
                                data-placeholder="Select Policy Slug">
                                <option value=""></option>
                                @foreach (\App\Models\PolicyCategory::orderBy('slug')->get() as $category)
                                    <option value="{{ $category->slug }}">
                                        {{ $category->slug }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="row">
        <div class="col-12">
            <div class="card border">
                <div class="card-body">
                    <table id="data-table" class="table table-bordered dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Name</th>
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
        $(function() {

            let table = $('#data-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.policy_categories.list') }}",
                    data: function(d) {
                        d.id = $('#filter_name').val();
                        d.slug = $('#filter_slug').val();
                    }
                },
                columns: [{
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'slug',
                        name: 'slug'
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function(data, type, row) {
                            return `
                        <a href="javascript:void(0);"
                        data-href="{{ route('admin.policy_categories.update.index') }}/${row.id}"
                        class="btn btn-soft-info open-remote-modal">
                            <i class="bx bx-pencil"></i>
                        </a>

                        <button class="btn btn-soft-danger delete-entry"
                                data-href="{{ route('admin.policy_categories.delete') }}/${row.id}">
                            <i class="bx bx-trash"></i>
                        </button>
                    `;
                        }
                    }
                ]
            });

            $('#filter_name, #filter_slug').on('change', function() {
                table.ajax.reload();
            });

            $('#reset-filter').on('click', function() {
                $('#filter_name').val('').trigger('change');
                $('#filter_slug').val('').trigger('change');
                table.ajax.reload();
            });

        });
    </script>
@endsection
