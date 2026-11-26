@extends('layouts.master')

@section('title')
    Update Policy
@endsection

<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Update Policy</h4>

                <div class="page-title-right">
                    <a href="{{ route('admin.policies.index') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <form id="updateFrm" enctype="multipart/form-data">
        @csrf

        <div class="row">

            <!-- LEFT -->
            <div class="col-lg-8">

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Policy Details</h4>
                    </div>

                    <div class="card-body">
                        <div class="row">

                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    Policy Category <span class="text-danger">*</span>
                                </label>
                                <select name="policy_category_id" class="form-control select2-class" required
                                    data-placeholder="Select Category">
                                    <option value=""></option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ $policy->policy_category_id == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    Policy Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control" value="{{ $policy->name }}"
                                    required>
                            </div>

                            <div class="col-lg-6 mt-3">
                                <div class="form-group">
                                    <label class="form-label">Slug <sup class="text-danger">*</sup></label>
                                    <input type="text" name="slug" class="form-control" value="{{ $policy->slug }}"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">Date</label>
                                <input type="date" name="date" class="form-control" value="{{ $policy->date }}">
                            </div>

                            <div class="col-md-12 mt-3">
                                <label class="form-label fw-bold">Description</label>
                                <textarea name="description" id="description" class="form-control" placeholder="Enter policy description...">{!! $policy->description !!}</textarea>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="col-lg-4">

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">Policy Image</h4>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <input type="file" name="image" class="dropify"
                                data-default-file="{{ $policy->image ? asset('storage/policy/' . $policy->image) : '' }}" />
                            <small class="text-muted">
                                <b>Recommended:</b> 250x250 px
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <button id="updateBtn" type="submit" class="btn btn-success w-100 mb-2">
                            Update Policy
                        </button>

                        <button type="reset" class="btn btn-warning w-100">
                            Reset
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </form>
@endsection


@section('script')
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>

    <script>
        $(document).ready(function() {

            $('input[name="name"]').on('keyup change', function() {
                let slug = $(this).val()
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');

                $('input[name="slug"]').val(slug);
            });

            $('#description').summernote({
                height: 250,
                placeholder: 'Enter blog description...',
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ]
            });

            $('#updateBtn').on('click', function(e) {
                e.preventDefault();

                let formData = new FormData($('#updateFrm')[0]);
                let btn = $(this);

                $.ajax({
                    url: "{{ route('admin.policies.update') }}/{{ $policy->id }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        btn.prop('disabled', true);
                        showToastr('info', 'Updating...');
                    },
                    success: function(res) {
                        showToastr('success', res.message);
                        window.location.href = "{{ route('admin.policies.index') }}";
                    },
                    error: function(xhr) {
                        showToastr('error', xhr.responseJSON?.message ||
                        'Something went wrong');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });

        });
    </script>
@endsection
