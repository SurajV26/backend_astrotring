@extends('layouts.master')

@section('title')
    Users
@endsection

@section('content')
    <!-- PAGE TITLE -->
    <div class="row">
        <div class="col-12">

            <div class="page-title-box d-sm-flex align-items-center justify-content-between">

                <h4 class="mb-sm-0 font-size-18">
                    All Users
                </h4>

                <div class="page-title-right">

                    <!-- EXPORT BUTTON -->
                    <button type="button" id="export-users-btn" class="btn btn-soft-success waves-effect waves-light me-1">
                        <i class="fas fa-file-excel"></i>
                        Export Excel
                    </button>

                    <!-- CREATE BUTTON -->
                    <a href="{{ route('admin.users.create.index') }}" class="btn btn-soft-info waves-effect waves-light">
                        <i class="fas fa-plus"></i>
                        Create
                    </a>

                </div>

            </div>

        </div>
    </div>


    <!-- FILTER SECTION -->
    <div class="row">

        <div class="col-12">

            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">

                    <h4 class="card-title mb-0">
                        Filter by
                    </h4>

                    <button type="button" id="reset-filter-btn" class="btn btn-light waves-effect waves-light">

                        <i class="fa fa-undo"></i>
                        Reset

                    </button>

                </div>


                <div class="card-body">

                    <div class="row">

                        <!-- USER FILTER -->
                        <div class="col">

                            <div class="form-group">

                                <label class="form-label fw-bold">
                                    User
                                </label>

                                <select id="user_id" class="form-control select2-class2" data-placeholder="Choose User">

                                    <option value=""></option>

                                    @foreach (\App\Models\User::where('type', 'user')->get() as $user)
                                        <option value="{{ $user->id }}">
                                            {{ $user->code }} - {{ $user->name }}
                                        </option>
                                    @endforeach

                                </select>

                            </div>

                        </div>


                        <!-- STATUS FILTER -->
                        <div class="col">

                            <div class="form-group">

                                <label class="form-label fw-bold">
                                    Status
                                </label>

                                <select id="status" class="form-control select2-class2" data-placeholder="Choose Status">

                                    <option value=""></option>

                                    <option value="1" selected>
                                        Active
                                    </option>

                                    <option value="0">
                                        Inactive
                                    </option>

                                </select>

                            </div>

                        </div>


                        <!-- DATE RANGE FILTER -->
                        {{-- <div class="col-md-4">

                            <div class="form-group">

                                <label class="form-label fw-bold">
                                    Date Range
                                </label>

                                <input type="text" id="date_range" class="form-control" placeholder="Choose Date Range"
                                    autocomplete="off">

                                <input type="hidden" id="from_date">

                                <input type="hidden" id="to_date">

                            </div>

                        </div> --}}

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- USERS TABLE -->
    <div class="row">

        <div class="col-12">

            <div class="card border">

                <div class="card-body">

                    <table id="data-table" class="table table-bordered dt-responsive nowrap w-100">

                        <thead>

                            <tr>

                                <th>
                                    User Details
                                </th>

                                <th>
                                    Username
                                </th>

                                <th>
                                    Email
                                </th>

                                <th>
                                    Mobile
                                </th>

                                <th>
                                    Status
                                </th>

                                <th width="100px">
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody></tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================= -->
    <!-- EXPORT DATE PICKER MODAL -->
    <!-- ========================================= -->

    <div class="modal fade" id="exportDateModal" tabindex="-1" aria-labelledby="exportDateModalLabel" aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <div class="modal-content">

                <!-- MODAL HEADER -->
                <div class="modal-header">

                    <h5 class="modal-title" id="exportDateModalLabel">

                        <i class="fas fa-file-excel text-success me-1"></i>

                        Export Users

                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>

                </div>


                <!-- MODAL BODY -->
                <div class="modal-body">

                    <div class="form-group">

                        <label class="form-label fw-bold">
                            Select Date Range
                        </label>

                        <input type="text" id="export_date_range" class="form-control" placeholder="Choose Date Range"
                            autocomplete="off">

                        <input type="hidden" id="export_from_date">

                        <input type="hidden" id="export_to_date">

                    </div>


                    <div class="mt-3">

                        <small class="text-muted">
                            Select a predefined range or choose
                            <strong>Custom</strong> to select your own dates.
                        </small>

                    </div>

                </div>


                <!-- MODAL FOOTER -->
                <div class="modal-footer">

                    <button type="button" class="btn btn-light waves-effect" data-bs-dismiss="modal">

                        Cancel

                    </button>


                    <button type="button" id="confirm-export-btn" class="btn btn-success waves-effect">

                        <i class="fas fa-file-excel"></i>

                        Export Excel

                    </button>

                </div>

            </div>

        </div>

    </div>
@endsection


@section('script')
    <script type="text/javascript">
        $(function() {

            /*
            |--------------------------------------------------------------------------
            | COMMON TABLE RELOAD
            |--------------------------------------------------------------------------
            */

            const reloadTable = (tableId) => {

                if (!tableId.startsWith('#')) {
                    tableId = `#${tableId}`;
                }

                $(tableId).DataTable().ajax.reload();

            };


            /*
            |--------------------------------------------------------------------------
            | MAIN DATE RANGE FILTER
            |--------------------------------------------------------------------------
            */

            $('#date_range').daterangepicker({

                autoUpdateInput: false,

                opens: 'left',

                locale: {
                    format: 'DD-MM-YYYY',
                    separator: ' - ',
                    applyLabel: 'Apply',
                    cancelLabel: 'Clear',
                    customRangeLabel: 'Custom'
                },

                ranges: {

                    'Today': [
                        moment(),
                        moment()
                    ],

                    'Yesterday': [
                        moment().subtract(1, 'days'),
                        moment().subtract(1, 'days')
                    ],

                    'Last 7 Days': [
                        moment().subtract(6, 'days'),
                        moment()
                    ],

                    'Last 15 Days': [
                        moment().subtract(14, 'days'),
                        moment()
                    ],

                    'Last Month': [
                        moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month')
                    ],

                    'This Month': [
                        moment().startOf('month'),
                        moment().endOf('month')
                    ],

                    'This Year': [
                        moment().startOf('year'),
                        moment().endOf('year')
                    ],

                    'Last Year': [
                        moment().subtract(1, 'year').startOf('year'),
                        moment().subtract(1, 'year').endOf('year')
                    ]

                }

            });


            /*
            |--------------------------------------------------------------------------
            | MAIN DATE RANGE APPLY
            |--------------------------------------------------------------------------
            */

            $('#date_range').on(
                'apply.daterangepicker',
                function(ev, picker) {

                    const fromDate =
                        picker.startDate.format('YYYY-MM-DD');

                    const toDate =
                        picker.endDate.format('YYYY-MM-DD');


                    $(this).val(

                        picker.startDate.format('DD-MM-YYYY') +
                        ' - ' +
                        picker.endDate.format('DD-MM-YYYY')

                    );


                    $('#from_date').val(fromDate);

                    $('#to_date').val(toDate);


                    // Reload users table
                    reloadTable('#data-table');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | MAIN DATE RANGE CLEAR
            |--------------------------------------------------------------------------
            */

            $('#date_range').on(
                'cancel.daterangepicker',
                function() {

                    $(this).val('');

                    $('#from_date').val('');

                    $('#to_date').val('');

                    reloadTable('#data-table');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | STATUS CHANGE
            |--------------------------------------------------------------------------
            */

            $('#data-table').on(
                'change',
                '.change-status',
                function(e) {

                    e.preventDefault();

                    const id = $(this).data('id');

                    const checkbox = $(this);

                    checkbox.prop('disabled', true);


                    $.get(
                        `{{ route('admin.users.change.status') }}/${id}`,
                        function(response) {

                            reloadTable('#data-table');

                            checkbox.prop('disabled', false);

                        }
                    ).fail(function() {

                        checkbox.prop('disabled', false);

                    });

                }
            );


            /*
            |--------------------------------------------------------------------------
            | RESET FILTER
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '#reset-filter-btn',
                function(e) {

                    e.preventDefault();


                    // User
                    $('#user_id')
                        .val('')
                        .trigger('change');


                    // Status
                    $('#status')
                        .val('1')
                        .trigger('change');


                    // Date range
                    $('#date_range').val('');

                    $('#from_date').val('');

                    $('#to_date').val('');


                    // Reload table
                    reloadTable('#data-table');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | DATATABLE
            |--------------------------------------------------------------------------
            */

            $('#data-table').DataTable({

                processing: true,

                serverSide: true,

                ajax: {

                    url: '{{ route('admin.users.list') }}',

                    data: function(d) {

                        /*
                        |--------------------------------------------------------------------------
                        | Existing Filters
                        |--------------------------------------------------------------------------
                        */

                        d.user_id =
                            $('#user_id').val();

                        d.status =
                            $('#status').val();


                        /*
                        |--------------------------------------------------------------------------
                        | Date Filters
                        |--------------------------------------------------------------------------
                        */

                        d.from_date =
                            $('#from_date').val();

                        d.to_date =
                            $('#to_date').val();

                    }

                },


                columns: [

                    /*
                    |--------------------------------------------------------------------------
                    | USER DETAILS
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: 'code_name',
                        name: 'code_name'
                    },


                    /*
                    |--------------------------------------------------------------------------
                    | USERNAME
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: 'username',
                        name: 'username'
                    },


                    /*
                    |--------------------------------------------------------------------------
                    | EMAIL
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: 'email',
                        name: 'email'
                    },


                    /*
                    |--------------------------------------------------------------------------
                    | MOBILE
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: 'mobile',
                        name: 'mobile'
                    },


                    /*
                    |--------------------------------------------------------------------------
                    | STATUS
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: null,

                        name: 'status',

                        className: 'text-center',

                        orderable: false,

                        searchable: false,

                        mRender: function(data, type, row) {

                            @if (Can::is_accessible('users', 'update'))

                                return `
                                <div class="square-switch">

                                    <input type="checkbox"
                                           id="status-switch-${row.id}"
                                           class="change-status"
                                           switch="status"
                                           data-id="${row.id}"
                                           ${row.status == 1 ? 'checked' : ''} />

                                    <label for="status-switch-${row.id}"
                                           data-on-label="Yes"
                                           data-off-label="No">
                                    </label>

                                </div>
                            `;
                            @else

                                return row.status == 1 ?
                                    'Active' :
                                    'Inactive';
                            @endif

                        }

                    },


                    /*
                    |--------------------------------------------------------------------------
                    | ACTION
                    |--------------------------------------------------------------------------
                    */

                    {
                        data: null,

                        className: 'text-center',

                        orderable: false,

                        searchable: false,

                        mRender: function(data, type, row) {

                            return `

                            <a href="{{ route('admin.users.update.index') }}/${row.id}"
                               class="btn btn-soft-info btn-sm waves-effect waves-light">

                                <i class="bx bx-pencil font-size-16"></i>

                            </a>


                            <a href="javascript:void(0);"
                               data-href="{{ route('admin.users.view') }}/${row.id}"
                               class="btn btn-soft-success btn-sm waves-effect waves-light open-remote-modal"
                               data-target="#xxlRemoteModal">

                                <i class="mdi mdi-eye font-size-16"></i>

                            </a>


                            <button type="button"
                                    class="btn btn-soft-danger btn-sm waves-effect waves-light delete-entry"
                                    data-href="{{ route('admin.users.delete') }}/${row.id}"
                                    data-tbl="data">

                                <i class="bx bx-trash font-size-16"></i>

                            </button>

                        `;

                        }

                    }

                ]

            });


            /*
            |--------------------------------------------------------------------------
            | NORMAL FILTER CHANGE
            |--------------------------------------------------------------------------
            */

            $('#user_id, #status').on(
                'change',
                function() {

                    reloadTable('#data-table');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | EXPORT DATE PICKER
            |--------------------------------------------------------------------------
            */

            $('#export_date_range').daterangepicker({

                autoUpdateInput: false,

                opens: 'left',

                locale: {

                    format: 'DD-MM-YYYY',

                    separator: ' - ',

                    applyLabel: 'Apply',

                    cancelLabel: 'Clear',

                    customRangeLabel: 'Custom'

                },

                ranges: {

                    /*
                    |--------------------------------------------------------------------------
                    | TODAY
                    |--------------------------------------------------------------------------
                    */

                    'Today': [
                        moment(),
                        moment()
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | YESTERDAY
                    |--------------------------------------------------------------------------
                    */

                    'Yesterday': [
                        moment().subtract(1, 'days'),
                        moment().subtract(1, 'days')
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | LAST 7 DAYS
                    |--------------------------------------------------------------------------
                    */

                    'Last 7 Days': [
                        moment().subtract(6, 'days'),
                        moment()
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | LAST 15 DAYS
                    |--------------------------------------------------------------------------
                    */

                    'Last 15 Days': [
                        moment().subtract(14, 'days'),
                        moment()
                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | LAST MONTH
                    |--------------------------------------------------------------------------
                    */

                    'Last Month': [

                        moment()
                        .subtract(1, 'month')
                        .startOf('month'),

                        moment()
                        .subtract(1, 'month')
                        .endOf('month')

                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | THIS MONTH
                    |--------------------------------------------------------------------------
                    */

                    'This Month': [

                        moment().startOf('month'),

                        moment().endOf('month')

                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | THIS YEAR
                    |--------------------------------------------------------------------------
                    */

                    'This Year': [

                        moment().startOf('year'),

                        moment().endOf('year')

                    ],


                    /*
                    |--------------------------------------------------------------------------
                    | LAST YEAR
                    |--------------------------------------------------------------------------
                    */

                    'Last Year': [

                        moment()
                        .subtract(1, 'year')
                        .startOf('year'),

                        moment()
                        .subtract(1, 'year')
                        .endOf('year')

                    ]

                }

            });


            /*
            |--------------------------------------------------------------------------
            | EXPORT DATE APPLY
            |--------------------------------------------------------------------------
            */

            $('#export_date_range').on(
                'apply.daterangepicker',
                function(ev, picker) {

                    const fromDate =
                        picker.startDate.format('YYYY-MM-DD');

                    const toDate =
                        picker.endDate.format('YYYY-MM-DD');


                    $(this).val(

                        picker.startDate.format('DD-MM-YYYY') +
                        ' - ' +
                        picker.endDate.format('DD-MM-YYYY')

                    );


                    $('#export_from_date')
                        .val(fromDate);

                    $('#export_to_date')
                        .val(toDate);

                }
            );


            /*
            |--------------------------------------------------------------------------
            | EXPORT DATE CLEAR
            |--------------------------------------------------------------------------
            */

            $('#export_date_range').on(
                'cancel.daterangepicker',
                function() {

                    $(this).val('');

                    $('#export_from_date').val('');

                    $('#export_to_date').val('');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | OPEN EXPORT MODAL
            |--------------------------------------------------------------------------
            */

            $('#export-users-btn').on(
                'click',
                function() {

                    /*
                    |--------------------------------------------------------------------------
                    | Clear previous export date
                    |--------------------------------------------------------------------------
                    */

                    $('#export_date_range').val('');

                    $('#export_from_date').val('');

                    $('#export_to_date').val('');


                    /*
                    |--------------------------------------------------------------------------
                    | Open modal
                    |--------------------------------------------------------------------------
                    */

                    $('#exportDateModal').modal('show');

                }
            );


            /*
            |--------------------------------------------------------------------------
            | EXPORT EXCEL
            |--------------------------------------------------------------------------
            */

            $('#confirm-export-btn').on(
                'click',
                function() {

                    const button = $(this);


                    /*
                    |--------------------------------------------------------------------------
                    | Get selected dates
                    |--------------------------------------------------------------------------
                    */

                    const fromDate =
                        $('#export_from_date').val();

                    const toDate =
                        $('#export_to_date').val();


                    /*
                    |--------------------------------------------------------------------------
                    | Date validation
                    |--------------------------------------------------------------------------
                    */

                    if (!fromDate || !toDate) {

                        alert(
                            'Please select a date range first.'
                        );

                        return;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Existing filters
                    |--------------------------------------------------------------------------
                    */

                    const userId =
                        $('#user_id').val();

                    const status =
                        $('#status').val();


                    /*
                    |--------------------------------------------------------------------------
                    | Export URL
                    |--------------------------------------------------------------------------
                    */

                    let url =
                        `{{ route('admin.users.export') }}`;


                    const params =
                        new URLSearchParams();


                    /*
                    |--------------------------------------------------------------------------
                    | Date
                    |--------------------------------------------------------------------------
                    */

                    params.append(
                        'from_date',
                        fromDate
                    );

                    params.append(
                        'to_date',
                        toDate
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | User
                    |--------------------------------------------------------------------------
                    */

                    if (userId) {

                        params.append(
                            'user_id',
                            userId
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Status
                    |--------------------------------------------------------------------------
                    */

                    if (status !== '') {

                        params.append(
                            'status',
                            status
                        );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Final URL
                    |--------------------------------------------------------------------------
                    */

                    url += '?' + params.toString();


                    /*
                    |--------------------------------------------------------------------------
                    | Disable button
                    |--------------------------------------------------------------------------
                    */

                    button.prop(
                        'disabled',
                        true
                    );

                    button.html(
                        '<i class="fas fa-spinner fa-spin"></i> Exporting...'
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Start download
                    |--------------------------------------------------------------------------
                    */

                    window.location.href = url;


                    /*
                    |--------------------------------------------------------------------------
                    | Close modal
                    |--------------------------------------------------------------------------
                    */

                    setTimeout(function() {

                        $('#exportDateModal').modal('hide');

                        button.prop(
                            'disabled',
                            false
                        );

                        button.html(
                            '<i class="fas fa-file-excel"></i> Export Excel'
                        );

                    }, 1500);

                }
            );

        });
    </script>
@endsection
