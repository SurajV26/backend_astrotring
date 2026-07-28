@extends('layouts.master')

@section('title')
    Update Astrologer
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Update Astrologer</h4>

                <div class="page-title-right">
                    <a href="{{ route('admin.astrologers.index') }}" class="btn btn-primary waves-effect waves-light"><i
                            class="fas fa-reply-all"></i> Back to list</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <form id="updateFrm">
                @csrf
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Astrologer Information</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="code" class="form-label fw-bold">Astrologer Code <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="code" name="code" class="form-control"
                                                placeholder="Enter Astrologer Id" value="{{ old('code', $astro->code) }}" />
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label fw-bold">Name <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="name" name="name" class="form-control"
                                                placeholder="Enter Name" value="{{ old('name', $astro->name) }}" />
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label fw-bold">Email Id <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="email" name="email" class="form-control"
                                                placeholder="Enter Email Id" value="{{ old('email', $astro->email) }}" />
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
                                                    placeholder="Enter Mobile No" value="{{ $astro->mobile }}">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="gender" class="form-label fw-bold">Gender :</label>
                                            <select id="gender" name="gender" class="form-control select2-class"
                                                data-placeholder="Select Gender">
                                                <option value=""></option>
                                                <option value="male"
                                                    {{ old('gender', $astro->gender) == 'male' ? 'selected' : '' }}>Male
                                                </option>
                                                <option value="female"
                                                    {{ old('gender', $astro->gender) == 'female' ? 'selected' : '' }}>
                                                    Female</option>
                                                <option value="other"
                                                    {{ old('gender', $astro->gender) == 'other' ? 'selected' : '' }}>Other
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- <div class="col-lg-4">
                                                <div class="form-group">
                                                    <label for="date_of_joining" class="form-label fw-bold">Date Of Joining
                                                        :</label>
                                                    <input type="date" name="date_of_joining" class="form-control"
                                                        value="{{ $astro->date_of_joining ? \Carbon\Carbon::parse($astro->date_of_joining)->format('Y-m-d') : '' }}">
                                                </div>
                                            </div> -->
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="dob" class="form-label fw-bold">Date Of Birth :</label>
                                            <input type="date" name="dob" class="form-control"
                                                value="{{ $astro->dob ? \Carbon\Carbon::parse($astro->dob)->format('Y-m-d') : '' }}">
                                        </div>
                                    </div>
                                    @php
                                        $selectedExpertise = old('expertise', $astro->expertise ?? []);
                                    @endphp
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Expertise <sup class="text-danger fs-5">*</sup> :
                                            </label>

                                            <select name="expertise[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Expertise">

                                                <option value=""></option>

                                                @foreach ([
            'signature_reading' => 'Signature Reading',
            'vedic' => 'Vedic',
            'tarot' => 'Tarot',
            'kp' => 'KP',
            'numerology' => 'Numerology',
            'lal_kitab' => 'Lal Kitab',
            'psychic' => 'Psychic',
            'palmistry' => 'Palmistry',
            'cartomancy' => 'Cartomancy',
            'prashana' => 'Prashana',
            'loshu_grid' => 'Loshu Grid',
            'nadi' => 'Nadi',
            'face_reading' => 'Face Reading',
            'horary' => 'Horary',
            'life_coach' => 'Life Coach',
            'western' => 'Western',
            'gemology' => 'Gemology',
            'vastu' => 'Vastu',
        ] as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ in_array($key, $selectedExpertise) ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>
                                    @php
                                        $selectedCategories = old('category', $astro->category ?? []);
                                    @endphp
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Category <sup class="text-danger fs-5">*</sup> :
                                            </label>

                                            <select name="category[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Category">

                                                <option value=""></option>

                                                @foreach ([
            'love' => 'Love',
            'marriage' => 'Marriage',
            'health' => 'Health',
            'wealth' => 'Wealth',
            'education' => 'Education',
            'career' => 'Career',
            'legal' => 'Legal',
            'remedies' => 'Remedies',
            'finance' => 'Finance',
            'parents' => 'Parents',
        ] as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ in_array($key, $selectedCategories) ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>
                                    @php
                                        $selectedEducation = old('astro_education', $astro->astro_education ?? []);
                                    @endphp

                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Astro Education <sup class="text-danger fs-5">*</sup> :
                                            </label>

                                            <select name="astro_education[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Astro Education">

                                                @foreach ([
            'self_learned' => 'Self Learned',
            'diploma' => 'Diploma in Astrology',
            'certified' => 'Certified Astrologer',
            'acharya' => 'Acharya in Astrology',
            'phd' => 'PhD in Astrology',
        ] as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ in_array($key, $selectedEducation ?? []) ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>
                                    @php
                                        $selectedLanguages = old('languages', $astro->languages ?? []);
                                    @endphp
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Languages <sup class="text-danger fs-5">*</sup> :
                                            </label>

                                            <select name="languages[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Languages">

                                                <option value=""></option>

                                                @foreach ([
            'english' => 'English',
            'hindi' => 'Hindi',
            'tamil' => 'Tamil',
            'punjabi' => 'Punjabi',
            'marathi' => 'Marathi',
            'gujarati' => 'Gujarati',
            'bengali' => 'Bengali',
            'french' => 'French',
            'odia' => 'Odia',
            'telugu' => 'Telugu',
            'kannada' => 'Kannada',
            'malayalam' => 'Malayalam',
            'sanskrit' => 'Sanskrit',
            'assamese' => 'Assamese',
            'german' => 'German',
            'spanish' => 'Spanish',
            'marwari' => 'Marwari',
            'manipuri' => 'Manipuri',
            'urdu' => 'Urdu',
            'sindhi' => 'Sindhi',
            'kashmiri' => 'Kashmiri',
            'bodo' => 'Bodo',
            'nepali' => 'Nepali',
            'konkani' => 'Konkani',
            'maithili' => 'Maithili',
            'arabic' => 'Arabic',
            'bhojpuri' => 'Bhojpuri',
            'dutch' => 'Dutch',
            'rajasthani' => 'Rajasthani',
        ] as $key => $label)
                                                    <option value="{{ $key }}"
                                                        {{ in_array($key, $selectedLanguages) ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="experience" class="form-label fw-bold">
                                                Experience (Years) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" name="experience" id="experience" class="form-control"
                                                min="0" value="{{ old('experience', $astro->experience) }}"
                                                placeholder="Enter experience in years">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="call_price" class="form-label fw-bold">
                                                Call Price (₹ / min) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" step="0.01" name="call_price" class="form-control"
                                                value="{{ old('call_price', $astro->call_price) }}">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="chat_price" class="form-label fw-bold">
                                                Chat Price (₹ / min) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" step="0.01" name="chat_price" class="form-control"
                                                value="{{ old('chat_price', $astro->chat_price) }}">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                How many hours you can contribute daily <sup
                                                    class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="daily_available_hours" class="form-control select2-class2">
                                                <option value=""></option>
                                                @foreach ([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12] as $hour)
                                                    <option value="{{ $hour }}"
                                                        {{ old('daily_available_hours', $astro->daily_available_hours) == $hour ? 'selected' : '' }}>
                                                        {{ $hour }} Hour{{ $hour > 1 ? 's' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Is there any astrologer in your family? <sup
                                                    class="text-danger fs-5">*</sup> :
                                            </label>

                                            <div class="d-flex gap-4 mt-2">
                                                <div class="form-check">
                                                    <input type="radio" name="is_family_astrologer" value="1"
                                                        {{ old('is_family_astrologer', $astro->is_family_astrologer) == 1 ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="family_yes"> Yes</label>
                                                </div>

                                                <div class="form-check">
                                                    <input type="radio" name="is_family_astrologer" value="0"
                                                        {{ old('is_family_astrologer', $astro->is_family_astrologer) == 0 ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="family_no"> No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12 d-none" id="family-details-wrapper">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Please describe (optional)
                                            </label>
                                            <textarea name="family_astrology_details" class="form-control">{{ old('family_astrology_details', $astro->family_astrology_details) }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Pincode :</label>
                                            <input type="number" name="pincode" class="form-control"
                                                placeholder="Enter Pin Code" value="{{ $astro->pincode }}">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="about" class="form-label fw-bold">About :</label>
                                            <textarea name="about" id="about" class="form-control" placeholder="Enter About Yourself">{{ old('about', $astro->about) }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label for="address" class="form-label fw-bold">Address :</label>
                                            <textarea name="address" id="address" class="form-control" placeholder="Enter Your Address">{{ old('address', $astro->address) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Astrologer Login Password</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="username" class="form-label fw-bold">Username <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="username" name="username" class="form-control"
                                                placeholder="Enter Username"
                                                value="{{ old('username', $astro->username) }}" />
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

                    <div class="col-lg-4">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Astrologer Rating Summary</h4>
                            </div>

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-center">

                                    <div>
                                        <strong>Average Rating :</strong><br>

                                        @if ($astro->rating > 0)
                                            <span class="text-warning" style="font-size: 20px;">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    @if ($i <= round($astro->rating))
                                                        ★
                                                    @else
                                                        ☆
                                                    @endif
                                                @endfor
                                            </span>
                                            <br>
                                            <small class="text-muted">{{ number_format($astro->rating, 1) }} out of
                                                5</small>
                                        @else
                                            <span class="text-muted">No ratings yet.</span>
                                        @endif
                                    </div>

                                    <div class="text-end">
                                        <strong>Total Reviews :</strong>
                                        <span class="badge bg-primary" style="font-size: 16px;">
                                            {{ $astro->rating_count }}
                                        </span>
                                    </div>

                                </div>

                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Advance Configuration</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="form-group d-flex justify-content-between align-items-center">
                                            <label class="form-label fw-bold">Published <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="hidden" name="status" value="0">
                                            <div class="square-switch">
                                                <input type="checkbox" id="square-status" name="status" switch="status"
                                                    value="1" {{ $astro->status ? 'checked' : '' }} />
                                                <label for="square-status" data-on-label="Yes"
                                                    data-off-label="No"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button id="updateBtn" type="submit"
                                    class="btn btn-success waves-effect waves-light w-100 mb-1">Save Changes</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">astro Image</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="file" name="profile_image" class="dropify"
                                                data-default-file="{{ $astro->profile_image ? asset('storage/user/' . $astro->profile_image) : '' }}" />
                                            <small class="text-muted"><b>Example::</b> image size - 128x128.</small>
                                        </div>
                                    </div>
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
    <script type="text/javascript">
        $(function() {
            // Handle Form Update Submission
            $(document).on('click', '#updateBtn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const formData = new FormData($('#updateFrm')[0]);

                $.ajax({
                    dataType: 'json',
                    type: 'POST',
                    url: "{{ route('admin.astrologers.update', $astro->id) }}",
                    data: formData,
                    processData: false,
                    contentType: false,

                    beforeSend: function() {
                        $btn.attr('disabled', true);
                        showToastr('info', 'Updating...');
                    },

                    success: function(response) {
                        showToastr('success', response.message);
                        location.replace("{{ route('admin.astrologers.index') }}");
                    },

                    error: function(jqXHR, exception) {
                        showToastr('error', formatErrorMessage(jqXHR, exception));
                    },

                    complete: function() {
                        $btn.attr('disabled', false);
                    }
                });

            });
        });
    </script>
    <script type="text/javascript">
        $(function() {
            $('input[name="is_family_astrologer"]').on('change', function() {
                if ($(this).val() == '1') {
                    $('#family-details-wrapper').removeClass('d-none');
                } else {
                    $('#family-details-wrapper').addClass('d-none');
                }
            });

            if ($('input[name="is_family_astrologer"]:checked').val() == '1') {
                $('#family-details-wrapper').removeClass('d-none');
            }

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const select = document.getElementById('country_code');
            const astroCountryCode = "{{ $astro->country_code }}";

            select.innerHTML = '';

            COUNTRY_CODES
                .sort((a, b) => a.country.localeCompare(b.country))
                .forEach(item => {

                    const option = document.createElement('option');

                    option.value = item.value;
                    option.textContent = item.label;

                     if (item.value === astroCountryCode) {
                        option.selected = true;
                    }

                    select.appendChild(option);
                });

        });
    </script>
@endsection
