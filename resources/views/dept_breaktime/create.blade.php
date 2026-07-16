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
                        Create Department Break Time
                    </h1>

                    <a href="{{ route('dept-breaktime.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>

                </div>

                <div class="card shadow mb-4">

                    <div class="card-header">

                        <h6 class="font-weight-bold text-primary">
                            Form Department Break Time
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

                        <form action="{{ route('dept-breaktime.store') }}" method="POST">

                            @csrf

                            <div class="form-group">

                                <label>Department</label>

                                <input
                                    type="text"
                                    name="id_dept"
                                    class="form-control"
                                    value="{{ old('id_dept') }}"
                                    placeholder="Contoh : PROD, QC, HRD">

                            </div>

                            <div class="form-group">

                                <label>Break Time</label>

                                <select name="id_break" class="form-control">

                                    <option value="">
                                        -- Pilih Break --
                                    </option>

                                    @foreach($breaks as $break)

                                        <option
                                            value="{{ $break->id }}"
                                            {{ old('id_break') == $break->id ? 'selected' : '' }}>

                                            {{ $break->sesi }}
                                            ({{ substr($break->time_start,0,5) }}
                                            -
                                            {{ substr($break->time_end,0,5) }})

                                        </option>

                                    @endforeach

                                </select>

                            </div>

                            <hr>

                            <button type="submit" class="btn btn-primary">

                                <i class="fas fa-save"></i>
                                Save

                            </button>

                            <a href="{{ route('dept-breaktime.index') }}" class="btn btn-secondary">

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