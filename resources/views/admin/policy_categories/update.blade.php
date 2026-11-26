<div id="update-wrapper">
    <div class="modal-header">
        <h5 class="modal-title">Update Policy Category</h5>
        <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">
        <form id="updateFrm" enctype="multipart/form-data">
            @csrf

            <div class="row">

                <div class="col-lg-12">
                    <div class="form-group">
                        <label class="form-label">Policy Category Name <sup class="text-danger">*</sup></label>
                        <input type="text" name="name" class="form-control" value="{{ $policyCategory->name }}"
                            placeholder="Enter Policy Category Name">
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="form-group">
                        <label class="form-label">Policy Category Slug <sup class="text-danger">*</sup></label>
                        <input type="text" name="slug" class="form-control" value="{{ $policyCategory->slug }}"
                            readonly>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <div class="modal-footer">
        <button id="updateBtn" type="button" class="btn btn-success">Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
</div>

<script>
    $('input[name="name"]').on('keyup change', function() {
        let slug = $(this).val()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');

        $('input[name="slug"]').val(slug);
    });

    // Submit update
    $('#update-wrapper').on('click', '#updateBtn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        let formData = new FormData($('#updateFrm')[0]);

        $.ajax({
            url: "{{ route('admin.policy_categories.update', $policyCategory->id) }}",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $btn.prop('disabled', true);
                showToastr('info', 'Updating...');
            },
            success: function(res) {
                showToastr('success', res.message);
                $('#data-table').DataTable().ajax.reload(null, false);
                $('#update-wrapper .close').click();
            },
            error: function(xhr) {
                showToastr('error', formatErrorMessage(xhr));
                $btn.prop('disabled', false);
            }
        });
    });
</script>
