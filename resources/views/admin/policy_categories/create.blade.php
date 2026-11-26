<div id="create-wrapper">
    <div class="modal-header">
        <h5 class="modal-title">Add Policy Category</h5>
        <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>

    <div class="modal-body">
        <form id="addFrm" enctype="multipart/form-data">
            @csrf

            <div class="row">

                <div class="col-lg-12">
                    <div class="form-group">
                        <label class="form-label">Policy Category Name <sup class="text-danger">*</sup></label>
                        <input type="text" name="name" class="form-control"
                            placeholder="Enter Policy Category Name">
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="form-group">
                        <label class="form-label">Slug <sup class="text-danger">*</sup></label>
                        <input type="text" name="slug" class="form-control"
                            placeholder="e.g. Auto generated from name" readonly>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <div class="modal-footer">
        <button id="createBtn" type="button" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
</div>

<script>
    // Auto generate slug from name
    $('input[name="name"]').on('keyup change', function() {
        let slug = $(this).val()
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9\s-]/g, '') // remove special chars
            .replace(/\s+/g, '-') // spaces → dash
            .replace(/-+/g, '-'); // multiple dashes

        $('input[name="slug"]').val(slug);
    });

    $('#create-wrapper').on('click', '#createBtn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const formData = new FormData($('#addFrm')[0]);

        $.ajax({
            url: "{{ route('admin.policy_categories.create') }}",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $btn.prop('disabled', true);
                showToastr('info', 'Saving...');
            },
            success: function(response) {
                showToastr('success', response.message);
                $('#data-table').DataTable().ajax.reload(null, false);
                $('#create-wrapper .close').click();
            },
            error: function(xhr) {
                showToastr('error', formatErrorMessage(xhr));
                $btn.prop('disabled', false);
            }
        });
    });
</script>
