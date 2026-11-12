<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    body {
        font-family: 'Nunito', sans-serif;
        background: linear-gradient(135deg, #4e73df 0%, #224abe 55%, #1a3a8f 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    .wa-login {
        background: #fff;
        border-radius: .9rem;
        box-shadow: 0 .9rem 2.4rem rgba(15, 30, 80, .3);
        width: 100%;
        max-width: 420px;
        padding: 1.9rem;
    }

    .wa-logo {
        height: 46px;
        width: auto;
        display: block;
        margin: 0 auto .7rem;
    }

    .wa-doc {
        font-size: .72rem;
        color: #6b7688;
        text-align: center;
        margin-bottom: 1.4rem;
    }
</style>

<body>

<div class="wa-login">
    <img src="{{ asset('img/chutex.svg') }}" alt="Chutex" class="wa-logo">
    <div class="text-center mb-1">
        <strong style="font-size:.88rem">PT. Chutex International Indonesia</strong>
    </div>
    <div class="wa-doc">Work Assignment Request &middot; CII.FM.HR.02.08</div>

    @include('sweetalert::alert')

    <form method="POST" action="{{ route('work-assignment.verify-manual') }}">
        @csrf

        <div class="form-group">
            <label for="npk" class="small font-weight-bold">NPK</label>
            <input type="text" id="npk" name="npk"
                   class="form-control @error('npk') is-invalid @enderror"
                   value="{{ old('npk') }}" placeholder="Cth: C-00825" required autofocus>
            @error('npk')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password" class="small font-weight-bold">Password</label>
            <input type="password" id="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Tanggal lahir (YYMMDD)" required>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">Contoh: lahir 5 Januari 1990 &rarr; <code>900105</code></small>
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-4 rounded-pill font-weight-bold">
            <i class="fas fa-sign-in-alt mr-1"></i> Masuk
        </button>
    </form>

    <div class="text-center mt-3">
        <small class="text-muted">
            Data karyawan (nama, jabatan, bagian, section) terisi otomatis dari NPK.
        </small>
    </div>
</div>

@include('layout.footerscript')

</body>
</html>
