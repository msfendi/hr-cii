<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body class="bg-gradient-primary">

    <div class="container container-center">
        <div class="text-center">
            <img src="{{ asset('img/chutex.svg') }}" style="width: 150px;">
            <h1 class="h4 text-white mb-4"><b>PT. Chutex International Indonesia</b></h1>
            <h1 class="h1 text-white mb-4"><b>E-Signature</b></h1>
        </div>
        <div class="card o-hidden border-0 shadow-lg my-5">
            <div class="card-body p-0">
                <!-- Nested Row within Card Body -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="p-5">
                            <div class="text-center">
                                <h1 class="h4 text-gray-900 mb-4">Create an Account!</h1>
                            </div>
                            <form class="user" action="{{ route('register.guest') }}" method="post">
                                @csrf
                                @if ($message = Session::get('success'))
                                <div class="alert alert-success alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('error'))
                                <div class="alert alert-danger alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('warning'))
                                <div class="alert alert-warning alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif

                                @if ($message = Session::get('info'))
                                <div class="alert alert-info alert-block">
                                    <button type="button" class="close" data-dismiss="alert">×</button>	
                                    <strong>{{ $message }}</strong>
                                </div>
                                @endif
                                <div class="form-group">
                                    <input type="name" class="form-control form-control-user @error('name') is-invalid @enderror" id="name" name="name"
                                        placeholder="Name" value="{{ old('name') }}">
                                    @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <input type="email" class="form-control form-control-user @error('email') is-invalid @enderror" id="email" name="email"
                                        placeholder="Email" value="{{ old('email') }}">
                                    @error('email')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="form-group">
                                    <input type="password" class="form-control form-control-user @error('password') is-invalid @enderror" id="password" name="password"
                                        placeholder="Password">
                                    @error('name')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary btn-user btn-block">Register</button>
                                </div>
                                <hr>
                            </form>
                            <div class="text-center">
                                <a class="small" href="{{ route('login') }}">Already have an account? Login!</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

@include('layout.footerscript')
</body>
</html>