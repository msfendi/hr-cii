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
                            Edit Break Master
                        </h1>

                        <a href="{{ route('break-master.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Back
                        </a>
                    </div>

                    <div class="card shadow mb-4">

                        <div class="card-header">
                            <h6 class="font-weight-bold text-primary">
                                Form Edit Break Master
                            </h6>
                        </div>

                        <div class="card-body">

                            @if ($errors->any())

                                <div class="alert alert-danger">

                                    <ul class="mb-0">

                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach

                                    </ul>

                                </div>

                            @endif

                            <form action="{{ route('break-master.update', $break->id) }}" method="POST">

                                @csrf
                                @method('PUT')

                                <div class="form-group">

                                    <label>Sesi</label>

                                    <input
                                        type="text"
                                        name="sesi"
                                        class="form-control"
                                        value="{{ old('sesi', $break->sesi) }}"
                                        placeholder="Contoh : Break 1">

                                </div>

                                <div class="form-group">

                                    <label>Jam Mulai</label>

                                    <input
                                        type="time"
                                        name="time_start"
                                        class="form-control"
                                        value="{{ old('time_start', \Carbon\Carbon::parse($break->time_start)->format('H:i')) }}">

                                </div>

                                <div class="form-group">

                                    <label>Jam Selesai</label>

                                    <input
                                        type="time"
                                        name="time_end"
                                        class="form-control"
                                        value="{{ old('time_end', \Carbon\Carbon::parse($break->time_end)->format('H:i')) }}">

                                </div>

                                <hr>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Update
                                </button>

                                <a href="{{ route('break-master.index') }}" class="btn btn-secondary">
                                    Cancel
                                </a>

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