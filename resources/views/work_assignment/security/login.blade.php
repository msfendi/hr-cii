<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<style>
    body {
        font-family: 'Nunito', sans-serif;
        background: linear-gradient(135deg, #1b3a6b 0%, #24507f 55%, #17325c 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    .wa-login {
        background: #fff;
        border-radius: .9rem;
        box-shadow: 0 .9rem 2.4rem rgba(10, 25, 60, .38);
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

    .wa-shield {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #e7f0fb;
        color: #24507f;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin: 0 auto .7rem;
    }

    .wa-alert {
        background: #fdecec;
        border: 1px solid #f5c2c2;
        color: #a4302c;
        border-radius: .5rem;
        padding: .6rem .8rem;
        font-size: .84rem;
        margin-bottom: 1rem;
    }

    .wa-hint {
        background: #f6f9ff;
        border: 1px solid #dbe6f7;
        border-radius: .5rem;
        padding: .55rem .7rem;
        font-size: .78rem;
        color: #4a5b73;
        margin-top: 1rem;
    }
</style>

<body>

<div class="wa-login">
    <div class="wa-shield"><i class="fas fa-user-shield"></i></div>

    <div class="text-center mb-1">
        <strong style="font-size:.9rem">Security Gate</strong>
    </div>
    <div class="wa-doc">Surat Ijin Dinas Luar &mdash; PT. Chutex International Indonesia</div>

    @if (session('wa_error'))
        <div class="wa-alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('wa_error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="wa-alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('work-assignment.security.verify') }}">
        @csrf

        <div class="form-group">
            <label for="npk" class="small font-weight-bold">NPK Petugas Security</label>
            <input type="text" id="npk" name="npk"
                   class="form-control @error('npk') is-invalid @enderror"
                   value="{{ old('npk') }}" placeholder="Cth: C-00857" required autofocus>
            @error('npk')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-block mt-3 rounded-pill font-weight-bold">
            <i class="fas fa-sign-in-alt mr-1"></i> Masuk
        </button>
    </form>

    <div class="wa-hint">
        <i class="fas fa-info-circle mr-1"></i>
        Nama petugas diambil otomatis dari NPK. Identitas ini yang tercatat
        saat Anda menyetujui karyawan keluar atau kembali.
    </div>
</div>

@include('layout.footerscript')

</body>
</html>