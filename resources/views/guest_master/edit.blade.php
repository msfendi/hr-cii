<!DOCTYPE html>
<html lang="en">

@include('layout.header')

<body id="page-top">

    <div id="wrapper">

        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">

            <div id="content">

                @include('layout.navbar')

                <div class="container-fluid">

                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            Edit Guest Master
                        </h1>
                    </div>

                    <div class="card shadow mb-4">

                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Form Guest Master
                            </h6>
                        </div>

                        <div class="card-body">

                            @if(session('success'))
                                <div class="alert alert-success">
                                    {{ session('success') }}
                                </div>
                            @endif

                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <form method="POST"
                                action="{{ route('guest-master.update') }}">

                                @csrf

                                <input type="hidden"
                                    name="id"
                                    value="{{ $data->id }}">

                                <div class="row">

                                    {{-- LEFT --}}
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Guest Name</label>
                                            <input type="text"
                                                class="form-control"
                                                value="{{ $data->guest_name }}"
                                                readonly>
                                        </div>

                                        <div class="form-group">
                                            <label>Gender</label>
                                            <select name="gender"
                                                class="form-control">
                                                <option value="">Select Gender</option>

                                                <option value="Male"
                                                    {{ $data->gender == 'Male' ? 'selected' : '' }}>
                                                    Male
                                                </option>

                                                <option value="Female"
                                                    {{ $data->gender == 'Female' ? 'selected' : '' }}>
                                                    Female
                                                </option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Place</label>
                                            <input type="text"
                                                name="place"
                                                class="form-control"
                                                value="{{ $data->place }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Date Of Birth</label>
                                            <input type="date"
                                                name="date_of_birth"
                                                class="form-control"
                                                value="{{ $data->date_of_birth }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Nationality</label>
                                            <input type="text"
                                                name="nationality"
                                                class="form-control"
                                                value="{{ $data->nationality }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Passport No</label>
                                            <input type="text"
                                                name="passport_no"
                                                class="form-control"
                                                value="{{ $data->passport_no }}">
                                        </div>

                                    </div>

                                    {{-- RIGHT --}}
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Issue Date</label>
                                            <input type="date"
                                                name="issue_date"
                                                class="form-control"
                                                value="{{ $data->issue_date }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Must Used Date</label>
                                            <input type="date"
                                                name="must_used_date"
                                                class="form-control"
                                                value="{{ $data->must_used_date }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Arrival Date</label>
                                            <input type="date"
                                                name="arrival_date"
                                                class="form-control"
                                                value="{{ $data->arrival_date }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Visa Expiry</label>
                                            <input type="date"
                                                name="visa_expiry"
                                                class="form-control"
                                                value="{{ $data->visa_expiry }}">
                                        </div>

                                        <div class="form-group">
                                            <label>Remark</label>
                                            <textarea name="remark"
                                                rows="5"
                                                class="form-control">{{ $data->remark }}</textarea>
                                        </div>

                                    </div>

                                </div>

                                <button type="submit"
                                    class="btn btn-primary btn-block">
                                    Update Guest
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

            @include('layout.footer')

        </div>

    </div>

</body>

</html>