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
                            Update Guest Master Detail
                        </h1>
                    </div>

                    <div class="card shadow mb-4">

                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Form Guest Master Detail
                            </h6>
                        </div>

                        <div class="card-body">

                            <form method="POST"
                                action="{{ route('guest-master.update') }}">

                                @csrf

                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row">

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Guest Name</label>
                                            <select name="id"
                                                class="form-control select2"
                                                required>

                                                @foreach($guests as $guest)
                                                    <option value="{{ $guest->id }}">
                                                        {{ $guest->guest_name }}
                                                    </option>
                                                @endforeach

                                            </select>
                                        </div>
                                    </div>

                                    {{-- LEFT --}}
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Gender</label>
                                            <select name="gender" class="form-control">
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Place</label>
                                            <input type="text"
                                                name="place"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Date Of Birth</label>
                                            <input type="date"
                                                name="date_of_birth"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Nationality</label>
                                            <input type="text"
                                                name="nationality"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Passport No</label>
                                            <input type="text"
                                                name="passport_no"
                                                class="form-control">
                                        </div>

                                    </div>

                                    {{-- RIGHT --}}
                                    <div class="col-md-6">

                                        <div class="form-group">
                                            <label>Issue Date</label>
                                            <input type="date"
                                                name="issue_date"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Must Used Date</label>
                                            <input type="date"
                                                name="must_used_date"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Arrival Date</label>
                                            <input type="date"
                                                name="arrival_date"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Visa Expiry</label>
                                            <input type="date"
                                                name="visa_expiry"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label>Remark</label>
                                            <textarea name="remark"
                                                rows="4"
                                                class="form-control"></textarea>
                                        </div>

                                    </div>

                                </div>

                                <button type="submit"
                                    class="btn btn-primary btn-block">
                                    Update Guest Master
                                </button>

                            </form>

                        </div>
                    </div>

                </div>
            </div>

            @include('layout.footer')

        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
        rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                width: '100%'
            });
        });
    </script>

</body>

</html>