@extends('layouts.master')

@section('title')
    Add Astrologer
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0 font-size-18">Add Astrologer</h4>

                <div class="page-title-right">
                    <a href="{{ route('admin.astrologers.index') }}" class="btn btn-primary waves-effect waves-light"><i
                            class="fas fa-reply-all"></i> Back</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <form id="createFrm">
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
                                                placeholder="Enter Astrologer Id" />
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label fw-bold">Name <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="name" name="name" class="form-control"
                                                placeholder="Enter name" />
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label fw-bold">Email Id <sup
                                                    class="text-danger fs-5">*</sup> :</label>
                                            <input type="text" id="email" name="email" class="form-control"
                                                placeholder="Enter Email Id" />
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
                                                    placeholder="Enter Mobile No">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label for="gender" class="form-label fw-bold">Gender :</label>
                                            <select id="gender" name="gender" class="form-control select2-class"
                                                data-placeholder="Select Gender">
                                                <option value=""></option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- <div class="col-lg-4">
                                                                    <div class="form-group">
                                                                        <label class="form-label fw-bold">Date Of Joining</label>
                                                                        <input type="date" name="date_of_joining" class="form-control">
                                                                    </div>
                                                                </div> -->
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">Date Of Birth</label>
                                            <input type="date" name="dob" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Expertise <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="expertise[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Expertise">
                                                <option value=""></option>
                                                <option value="signature_reading">Signature Reading</option>
                                                <option value="vedic">Vedic</option>
                                                <option value="tarot">Tarot</option>
                                                <option value="kp">KP</option>
                                                <option value="numerology">Numerology</option>
                                                <option value="lal_kitab">Lal Kitab</option>
                                                <option value="psychic">Psychic</option>
                                                <option value="palmistry">Palmistry</option>
                                                <option value="cartomancy">Cartomancy</option>
                                                <option value="prashana">Prashana</option>
                                                <option value="loshu_grid">Loshu Grid</option>
                                                <option value="nadi">Nadi</option>
                                                <option value="face_reading">Face Reading</option>
                                                <option value="horary">Horary</option>
                                                <option value="life_coach">Life Coach</option>
                                                <option value="western">Western</option>
                                                <option value="gemology">Gemology</option>
                                                <option value="vastu">Vastu</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Category <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="category[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Category">
                                                <option value=""></option>
                                                <option value="love">Love</option>
                                                <option value="marriage">Marriage</option>
                                                <option value="health">Health</option>
                                                <option value="wealth">Wealth</option>
                                                <option value="education">Education</option>
                                                <option value="career">Career</option>
                                                <option value="legal">Legal</option>
                                                <option value="remedies">Remedies</option>
                                                <option value="finance">Finance</option>
                                                <option value="parents">Parents</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Astro Education <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="astro_education[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Astro Education">
                                                <option value=""></option>
                                                <option value="self_learned">Self Learned</option>
                                                <option value="diploma">Diploma in Astrology</option>
                                                <option value="certified">Certified Astrologer</option>
                                                <option value="acharya">Acharya in Astrology</option>
                                                <option value="phd">PhD in Astrology</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                Languages <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="languages[]" class="form-control select2-class" multiple
                                                data-placeholder="Select Languages">
                                                <option value=""></option>
                                                <option value="english">English</option>
                                                <option value="hindi">Hindi</option>
                                                <option value="tamil">Tamil</option>
                                                <option value="punjabi">Punjabi</option>
                                                <option value="marathi">Marathi</option>
                                                <option value="gujarati">Gujarati</option>
                                                <option value="bengali">Bengali</option>
                                                <option value="french">French</option>
                                                <option value="odia">Odia</option>
                                                <option value="telugu">Telugu</option>
                                                <option value="kannada">Kannada</option>
                                                <option value="malayalam">Malayalam</option>
                                                <option value="sanskrit">Sanskrit</option>
                                                <option value="assamese">Assamese</option>
                                                <option value="german">German</option>
                                                <option value="spanish">Spanish</option>
                                                <option value="marwari">Marwari</option>
                                                <option value="manipuri">Manipuri</option>
                                                <option value="urdu">Urdu</option>
                                                <option value="sindhi">Sindhi</option>
                                                <option value="kashmiri">Kashmiri</option>
                                                <option value="bodo">Bodo</option>
                                                <option value="nepali">Nepali</option>
                                                <option value="konkani">Konkani</option>
                                                <option value="maithili">Maithili</option>
                                                <option value="arabic">Arabic</option>
                                                <option value="bhojpuri">Bhojpuri</option>
                                                <option value="dutch">Dutch</option>
                                                <option value="rajasthani">Rajasthani</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="experience" class="form-label fw-bold">
                                                Experience (Years) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" name="experience" id="experience" class="form-control"
                                                min="0" placeholder="Enter experience in years">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="call_price" class="form-label fw-bold">
                                                Call Price (₹ / min) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" step="0.01" min="0" name="call_price"
                                                id="call_price" class="form-control" placeholder="e.g. 15.00">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label for="chat_price" class="form-label fw-bold">
                                                Chat Price (₹ / min) <sup class="text-danger fs-5">*</sup> :
                                            </label>
                                            <input type="number" step="0.01" min="0" name="chat_price"
                                                id="chat_price" class="form-control" placeholder="e.g. 10.00">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                How many hours you can contribute daily <sup
                                                    class="text-danger fs-5">*</sup> :
                                            </label>
                                            <select name="daily_available_hours" class="form-control select2-class2"
                                                data-placeholder="Please Select Option">
                                                <option value=""></option>
                                                <option value="1">1 Hour</option>
                                                <option value="2">2 Hours</option>
                                                <option value="3">3 Hours</option>
                                                <option value="4">4 Hours</option>
                                                <option value="5">5 Hours</option>
                                                <option value="6">6 Hours</option>
                                                <option value="8">8 Hours</option>
                                                <option value="9">9 Hours</option>
                                                <option value="10">10 Hours</option>
                                                <option value="11">11 Hours</option>
                                                <option value="12">12 Hours</option>
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
                                                    <input class="form-check-input" type="radio"
                                                        name="is_family_astrologer" id="family_yes" value="1">
                                                    <label class="form-check-label" for="family_yes"> Yes</label>
                                                </div>

                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio"
                                                        name="is_family_astrologer" id="family_no" value="0"
                                                        checked>
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
                                            <textarea name="family_astrology_details" class="form-control" placeholder="Father / Mother / Grandfather etc."></textarea>
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
                                            <label for="about" class="form-label fw-bold">About :</label>
                                            <textarea name="about" id="about" class="form-control" placeholder="Enter About Your Self"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label for="address" class="form-label fw-bold">Address :</label>
                                            <textarea name="address" id="address" name="address" class="form-control" placeholder="Enter Your Address"></textarea>
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

                    <div class="col-lg-4">
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
                                                    value="1" checked />
                                                <label for="square-status" data-on-label="Yes"
                                                    data-off-label="No"></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button id="createBtn" type="submit"
                                    class="btn btn-success waves-effect waves-light w-100 mb-1">Save</button>
                                <button type="reset"
                                    class="btn w-100 btn-warning waves-effect waves-light">Clear</button>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Astrologer Image</h4>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-sm-12">
                                        <div class="form-group">
                                            <input type="file" name="profile_image" class="dropify" />
                                            <small class="text-muted"><b>Example::</b> image size - 250x250.</small>
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
        $(document).ready(function() {

            // Handle Form Submission
            $('#createBtn').on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const formData = new FormData($('#createFrm')[0]);

                $.ajax({
                    url: "{{ route('admin.astrologers.create') }}",
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
                        window.location.href = "{{ route('admin.astrologers.index') }}";
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
        $('input[name="is_family_astrologer"]').on('change', function() {
            if ($(this).val() == '1') {
                $('#family-details-wrapper').removeClass('d-none');
            } else {
                $('#family-details-wrapper').addClass('d-none');
            }
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
@endsection
