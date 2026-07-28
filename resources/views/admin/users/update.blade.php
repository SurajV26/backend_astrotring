@extends('layouts.master')

@section('title')
    Update User
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Update User</h4>

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
            <form id="updateUserForm">
                @csrf
                <input type="hidden" name="id" value="{{ $user->id }}">

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
                                                placeholder="Enter User Id" value="{{ $user->code }}" />
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Full Name <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" name="name" class="form-control"
                                                placeholder="Enter full name" value="{{ $user->name }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Email <sup class="text-danger fs-5">*</sup>
                                                :</label>
                                            <input type="email" name="email" class="form-control"
                                                placeholder="Enter Email" value="{{ $user->email }}">
                                        </div>
                                    </div>

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
                                                    placeholder="Enter Mobile No" value="{{ $user->mobile }}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Date of Birth :</label>
                                            <input type="date" name="dob" class="form-control"
                                                value="{{ $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : '' }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Birth Time :</label>
                                            <input type="time" name="birth_time" class="form-control"
                                                value="{{ $user->birth_time }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Birth Place :</label>

                                            <input type="text" id="birth_place_search" class="form-control"
                                                placeholder="Search Birth Place..." autocomplete="off"
                                                value="{{ $user->birth_place['displayName'] ?? '' }}">

                                            <input type="hidden" id="birth_place" name="birth_place"
                                                value='@json($user->birth_place)'>

                                            <div id="birthPlaceList" class="list-group position-absolute w-100 shadow"
                                                style="z-index:9999;display:none;"></div>

                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <label class="fw-bold">Gender :</label>
                                        <select name="gender" class="form-control select2-class">
                                            <option value=""></option>
                                            <option value="male" {{ $user->gender == 'male' ? 'selected' : '' }}>Male
                                            </option>
                                            <option value="female" {{ $user->gender == 'female' ? 'selected' : '' }}>
                                                Female
                                            </option>
                                            <option value="other" {{ $user->gender == 'other' ? 'selected' : '' }}>Other
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Pincode :</label>
                                            <input type="number" name="pincode" class="form-control"
                                                placeholder="Enter Pin Code" value="{{ $user->pincode }}">
                                        </div>
                                    </div>

                                    <div class="col-lg-6">
                                        <label class="fw-bold">Address :</label>
                                        <textarea name="address" class="form-control">{{ $user->address }}</textarea>
                                    </div>

                                    <div class="col-lg-6">
                                        <label class="fw-bold">About :</label>
                                        <textarea name="about" class="form-control">{{ $user->about }}</textarea>
                                    </div>

                                </div>

                            </div>
                        </div>

                        <!-- WALLET SECTION -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Wallet Information</h4>
                            </div>
                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-6">
                                        <label class="fw-bold">Wallet Balance (₹) :</label>
                                        <input type="number" step="0.01" name="balance"
                                            value="{{ $user->wallet->balance ?? 0 }}" class="form-control">
                                    </div>

                                    <div class="col-lg-6">
                                        <label class="fw-bold">Last Recharge Amount (₹) :</label>
                                        <input type="text" value="{{ $user->wallet->last_recharge_amount ?? 0 }}"
                                            class="form-control" readonly>
                                    </div>

                                </div>

                            </div>
                        </div>

                        <!-- PASSWORD -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">user Login Password</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="username" class="form-label fw-bold">Username <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="username" name="username" class="form-control"
                                                placeholder="Enter Username"
                                                value="{{ old('username', $user->username) }}" />
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="password" class="form-label fw-bold">Password <sup
                                                    class="text-danger fs-5"></sup> :</label>
                                            <div class="input-group auth-pass-inputgroup">
                                                <input type="password" id="password" name="password"
                                                    class="form-control" placeholder="Enter Password" />
                                                <button class="btn btn-light" type="button" id="password-addon"><i
                                                        class="mdi mdi-eye-outline"></i></button>
                                                <label class="text-danger fw-bold">
                                                    Password (Leave blank to keep unchanged)
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="cnfrm_password" class="form-label fw-bold">Confirm Password <sup
                                                    class="text-danger fs-5"></sup> :</label>
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
                                <div class="col-lg-12">
                                    <div class="form-group d-flex justify-content-between align-items-center">
                                        <label class="form-label fw-bold">Status <sup class="text-danger fs-5">*</sup>
                                            :</label>
                                        <input type="hidden" name="status" value="0">
                                        <div class="square-switch">
                                            <input type="checkbox" id="square-status" name="status" switch="status"
                                                value="1" {{ $user->status ? 'checked' : '' }} />
                                            <label for="square-status" data-on-label="Yes" data-off-label="No"></label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="submit" id="updateBtn" class="btn btn-success w-100">Save Changes</button>
                            </div>
                        </div>

                        <!-- PROFILE IMAGE -->
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Profile Image</h4>
                            </div>
                            <div class="card-body">
                                <input type="file" name="profile_image" class="dropify"
                                    data-default-file="{{ $user->profile_image ? asset('storage/user/' . $user->profile_image) : '' }}">
                                <small class="text-muted">Recommended: 250x250 px</small>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header d-flex justify-content-between">
                                <h4 class="card-title mb-0">Ratings & Reviews Given</h4>

                                <button type="button" class="btn btn-sm btn-primary" id="addReviewBtn">
                                    Add Review
                                </button>
                            </div>

                            <div class="card-body">

                                <!-- Add Review Form -->
                                <div id="addReviewForm" class="border p-3 mb-3 d-none">

                                    <div class="form-group mb-2">
                                        <label class="fw-bold">Select Astrologer</label>
                                        <select id="new_review_astro" class="form-control select2-class"
                                            data-placeholder="Choose Astrologer">
                                            <option value=""></option>
                                            @foreach (\App\Models\User::where('type', 'astro')->get() as $astro)
                                                <option value="{{ $astro->id }}">{{ $astro->name }}
                                                    ({{ $astro->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group mb-2">
                                        <label class="fw-bold">Rating</label>
                                        <select id="new_review_rating" class="form-control select2-class"
                                            data-placeholder="Choose Rating">
                                            <option value=""></option>
                                            <option value="1">1 Star</option>
                                            <option value="2">2 Stars</option>
                                            <option value="3">3 Stars</option>
                                            <option value="4">4 Stars</option>
                                            <option value="5">5 Stars</option>
                                        </select>
                                    </div>

                                    <div class="form-group mb-2">
                                        <label class="fw-bold">Review Message</label>
                                        <textarea id="new_review_text" class="form-control" rows="3" placeholder="Enter message"></textarea>
                                    </div>

                                    <button type="button" class="btn btn-success w-100" id="saveReviewBtn">
                                        Save Review
                                    </button>
                                </div>


                                <!-- EDIT EXISTING REVIEWS -->
                                @forelse($user->reviews as $review)
                                    <div class="border rounded p-3 mb-3">

                                        <input type="hidden" name="reviews[{{ $review->id }}][id]"
                                            value="{{ $review->id }}">

                                        <div class="form-group mb-2">
                                            <label class="fw-bold">Astrologer :</label>
                                            <select name="reviews[{{ $review->id }}][astrologer_id]"
                                                class="form-control select2-class">
                                                @foreach (\App\Models\User::where('type', 'astro')->get() as $astro)
                                                    <option value="{{ $astro->id }}"
                                                        {{ $review->astrologer_id == $astro->id ? 'selected' : '' }}>
                                                        {{ $astro->name }} ({{ $astro->code }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label class="fw-bold">Rating :</label>
                                            <select name="reviews[{{ $review->id }}][rating]"
                                                class="form-control select2-class">
                                                @foreach (range(1, 5) as $i)
                                                    <option value="{{ $i }}"
                                                        {{ $review->rating == $i ? 'selected' : '' }}>
                                                        {{ str_repeat('★', $i) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group mb-2">
                                            <label class="fw-bold">Review Message :</label>
                                            <textarea name="reviews[{{ $review->id }}][review]" class="form-control" rows="3">{{ $review->review }}</textarea>
                                        </div>

                                        <div class="form-check mt-2">
                                            <input type="checkbox" class="form-check-input"
                                                id="delete_review_{{ $review->id }}"
                                                name="reviews[{{ $review->id }}][delete]" value="1">
                                            <label class="form-check-label text-danger fw-bold"
                                                for="delete_review_{{ $review->id }}">
                                                Delete this review
                                            </label>
                                        </div>

                                    </div>

                                @empty
                                    <p class="text-muted">No reviews yet.</p>
                                @endforelse

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

            $("#addReviewBtn").click(function() {
                $("#addReviewForm").toggleClass("d-none");
            });

            $("#saveReviewBtn").click(function() {

                let astro = $("#new_review_astro").val();
                let rating = $("#new_review_rating").val();
                let text = $("#new_review_text").val();

                if (!astro || !rating) {
                    showToastr('error', "Select astrologer and rating");
                    return;
                }

                let reviewHtml = `
            <div class="border rounded p-3 mb-3">

                <input type="hidden" name="new_review_astrologer_id" value="${astro}">
                <input type="hidden" name="new_review_rating" value="${rating}">
                <input type="hidden" name="new_review_text" value="${text}">

                <p><strong>Astrologer:</strong> ${$("#new_review_astro option:selected").text()}</p>
                <p><strong>Rating:</strong> ${rating} Stars</p>
                <p><strong>Review:</strong> ${text}</p>
            </div>
        `;

                $("#addReviewForm").after(reviewHtml);

                $("#new_review_astro").val('').trigger("change");
                $("#new_review_rating").val('');
                $("#new_review_text").val('');

                $("#addReviewForm").addClass("d-none");
            });

            $('#updateBtn').click(function(e) {
                e.preventDefault();

                let formData = new FormData($('#updateUserForm')[0]);
                let $btn = $(this);

                $.ajax({
                    url: "{{ route('admin.users.update', $user->id) }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function() {
                        $btn.prop('disabled', true);
                        showToastr('info', 'Updating...');
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
            const userCountryCode = "{{ $user->country_code }}";

            select.innerHTML = '';

            COUNTRY_CODES
                .sort((a, b) => a.country.localeCompare(b.country))
                .forEach(item => {

                    const option = document.createElement('option');

                    option.value = item.value;
                    option.textContent = item.label;

                    if (item.value === userCountryCode) {
                        option.selected = true;
                    }

                    select.appendChild(option);
                });

        });
    </script>
    <script>
        const searchInput = document.getElementById('birth_place_search');
        const hiddenInput = document.getElementById('birth_place');
        const resultBox = document.getElementById('birthPlaceList');

        let typingTimer;

        searchInput.addEventListener('input', function() {

            clearTimeout(typingTimer);

            const keyword = this.value.trim();

            if (keyword.length < 2) {
                resultBox.innerHTML = '';
                resultBox.style.display = 'none';
                return;
            }

            typingTimer = setTimeout(() => {

                fetch(
                        `https://jagannatha-hora-359167915530.europe-west1.run.app/location/autocomplete?q=${encodeURIComponent(keyword)}`
                        )
                    .then(response => response.json())
                    .then(data => {

                        resultBox.innerHTML = '';

                        if (!data.results || data.results.length === 0) {
                            resultBox.style.display = 'none';
                            return;
                        }

                        data.results.forEach(place => {

                            const item = document.createElement('a');

                            item.href = "javascript:void(0)";
                            item.className = "list-group-item list-group-item-action";
                            item.textContent = place.displayName;

                            item.onclick = function() {

                                searchInput.value = place.displayName;

                                // pura JSON save hoga
                                hiddenInput.value = JSON.stringify(place);

                                resultBox.style.display = "none";
                            };

                            resultBox.appendChild(item);
                        });

                        resultBox.style.display = 'block';
                    })
                    .catch(err => {
                        console.error(err);
                        resultBox.style.display = 'none';
                    });

            }, 300);

        });

        document.addEventListener('click', function(e) {

            if (!resultBox.contains(e.target) && e.target !== searchInput) {
                resultBox.style.display = 'none';
            }

        });
    </script>
@endsection
