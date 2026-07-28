@extends('layouts.master')

@section('title')
    Add User
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Add User</h4>

                <div class="page-title-right">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-primary waves-effect waves-light">
                        <i class="fas fa-reply-all"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <form id="createUserForm">
                @csrf
                <div class="row">

                    <!-- LEFT SIDE -->
                    <div class="col-lg-8">

                        <!-- USER INFORMATION -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">User Information</h4>
                            </div>
                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="code" class="form-label fw-bold">User Code <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="code" name="code" class="form-control"
                                                placeholder="Enter User Id" />
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Full Name <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" name="name" class="form-control"
                                                placeholder="Enter full name">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Email <sup class="text-danger fs-5">*</sup>
                                                :</label>
                                            <input type="email" name="email" class="form-control"
                                                placeholder="Enter Email">
                                        </div>
                                    </div>

                                    <!--<div class="col-lg-4">-->
                                    <!--    <div class="form-group">-->
                                    <!--        <label class="form-label fw-bold">Mobile <sup class="text-danger fs-5">*</sup> :</label>-->
                                    <!--        <input type="text" name="mobile" class="form-control" maxlength="10" placeholder="Enter Mobile No">-->
                                    <!--    </div>-->
                                    <!--</div>-->
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Mobile <sup class="text-danger fs-5">*</sup> :
                                            </label>

                                            <div class="input-group">
                                                <select name="country_code" id="country_code" class="form-select"
                                                    style="max-width:120px">
                                                    <option value="">Loading...</option>
                                                </select>

                                                <input type="text" name="mobile" class="form-control" maxlength="10"
                                                    placeholder="Enter Mobile No">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Date of Birth :</label>
                                            <input type="date" name="dob" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Birth Time :</label>
                                            <input type="time" name="birth_time" class="form-control">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Birth Place :</label>

                                            <input type="text" id="birth_place_search" class="form-control"
                                                placeholder="Search Birth Place..." autocomplete="off">

                                            <input type="hidden" id="birth_place" name="birth_place">

                                            <div id="birthPlaceList" class="list-group position-absolute w-100 shadow"
                                                style="z-index:9999;display:none;max-height:250px;overflow-y:auto;">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Gender :</label>
                                            <select name="gender" class="form-control select2-class"
                                                data-placeholder="Select Gender">
                                                <option value=""></option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Pincode :</label>
                                            <input type="number" name="pincode" class="form-control"
                                                placeholder="Enter Pin Code">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">About :</label>
                                            <textarea name="about" class="form-control" placeholder="About the User (optional)"></textarea>
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Address :</label>
                                            <textarea name="address" class="form-control" placeholder="Enter Address"></textarea>
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>

                        <!-- PASSWORD SECTION -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">User Login Password</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="username" class="form-label fw-bold">Username <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="username" name="username" class="form-control"
                                                placeholder="Enter Username" />
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="password" class="form-label fw-bold">Password <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" id="password" name="password"
                                                    class="form-control" placeholder="Enter Password" />
                                                <button class="btn btn-light" type="button" id="password-addon"><i
                                                        class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="cnfrm_password" class="form-label fw-bold">Confirm Password <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" id="cnfrm_password" name="password_confirmation"
                                                    class="form-control" placeholder="Enter Password Again" />
                                                <button class="btn btn-light" type="button" id="password-addon"><i
                                                        class="mdi mdi-eye-outline"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT SIDE -->
                    <div class="col-lg-4">

                        <!-- STATUS -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">User Status</h4>
                            </div>

                            <div class="card-body">
                                <div class="form-group d-flex justify-content-between align-items-center">
                                    <label class="form-label fw-bold">Status</label>
                                    <input type="hidden" name="status" value="0">

                                    <div class="square-switch">
                                        <input type="checkbox" id="switch-status" name="status" switch="status"
                                            value="1" checked>
                                        <label for="switch-status" data-on-label="Yes" data-off-label="No"></label>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" id="createBtn" class="btn btn-success w-100 mb-1">Save</button>
                                <button type="reset" class="btn btn-warning w-100">Clear</button>
                            </div>
                        </div>

                        <!-- USER IMAGE -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">User Profile Image</h4>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <input type="file" name="profile_image" class="dropify" />
                                    <small class="text-muted"><b>Recommended:</b> 250x250 px</small>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('js/countryCodes.js') }}"></script>
    <script>
        $(function() {

            $('#createBtn').click(function(e) {
                e.preventDefault();

                let formData = new FormData($('#createUserForm')[0]);
                let $btn = $(this);

                $.ajax({
                    url: "{{ route('admin.users.create') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $btn.prop('disabled', true);
                        showToastr('info', 'Processing...');
                    },
                    success: function(response) {
                        showToastr('success', response.message);
                        window.location.href = "{{ route('admin.users.index') }}";
                    },
                    error: function(jqXHR, exception) {
                        showToastr('error', formatErrorMessage(jqXHR, exception));
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });

            });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const select = document.getElementById('country_code');
            select.innerHTML = '';

            COUNTRY_CODES
                .sort((a, b) => a.country.localeCompare(b.country))
                .forEach(item => {

                    const option = document.createElement('option');

                    option.value = item.value;
                    option.textContent = item.label;

                    if (item.value === '+91') {
                        option.selected = true;
                    }

                    select.appendChild(option);
                });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const searchInput = document.getElementById('birth_place_search');
            const hiddenInput = document.getElementById('birth_place');
            const resultBox = document.getElementById('birthPlaceList');

            let timer;

            searchInput.addEventListener('input', function() {

                clearTimeout(timer);

                const keyword = this.value.trim();

                if (keyword.length < 2) {
                    resultBox.innerHTML = '';
                    resultBox.style.display = 'none';
                    return;
                }

                timer = setTimeout(function() {

                    fetch(
                            `https://jagannatha-hora-359167915530.europe-west1.run.app/location/autocomplete?q=${encodeURIComponent(keyword)}`
                            )
                        .then(res => res.json())
                        .then(data => {

                            resultBox.innerHTML = '';

                            if (!data.results || data.results.length === 0) {
                                resultBox.style.display = 'none';
                                return;
                            }

                            data.results.forEach(place => {

                                const item = document.createElement('a');

                                item.href = "javascript:void(0)";
                                item.className =
                                    "list-group-item list-group-item-action";
                                item.textContent = place.displayName;

                                item.onclick = function() {

                                    searchInput.value = place.displayName;

                                    // hidden field me pura JSON
                                    hiddenInput.value = JSON.stringify(place);

                                    resultBox.style.display = "none";
                                };

                                resultBox.appendChild(item);

                            });

                            resultBox.style.display = "block";

                        })
                        .catch(error => {
                            console.error(error);
                            resultBox.style.display = "none";
                        });

                }, 300);

            });

            document.addEventListener('click', function(e) {

                if (!resultBox.contains(e.target) && e.target !== searchInput) {
                    resultBox.style.display = 'none';
                }

            });

        });
    </script>
@endsection
