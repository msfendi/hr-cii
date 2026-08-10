@include('layout.header')
<style>
    :root{
        --merah: #C8102E;
        --merah-tua: #7a0000;
        --gold: #FFD700;
        --abu: #6c757d;
    }
    html, body{
        height: 100%;
        font-family: 'Poppins', sans-serif;
    }
    body.bg-expired{
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at top, #4a4a4a 0%, #1c1c1c 65%, #0d0d0d 100%);
        padding: 1.5rem;
    }
    .expired-card{
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 20px 50px rgba(0,0,0,.35);
        max-width: 460px;
        width: 100%;
        text-align: center;
        overflow: hidden;
    }
    .expired-header{
        background: linear-gradient(135deg, var(--merah) 0%, var(--merah-tua) 100%);
        padding: 2rem 1.5rem 1.5rem;
        color: #fff;
    }
    .expired-header img{
        width: 90px;
        margin-bottom: .75rem;
    }
    .expired-icon{
        font-size: 4rem;
        line-height: 1;
        margin-bottom: .5rem;
        opacity: .95;
    }
    .expired-body{
        padding: 2rem 1.75rem 2.25rem;
    }
    .expired-body h1{
        font-size: 1.35rem;
        font-weight: 700;
        color: #333;
        margin-bottom: .6rem;
    }
    .expired-body p{
        color: #777;
        font-size: .92rem;
        margin-bottom: 1.5rem;
    }
    .expired-body .event-name{
        display: inline-block;
        background: #f5f5f5;
        border-radius: .5rem;
        padding: .5rem 1rem;
        font-weight: 600;
        color: #444;
        margin-bottom: 1.5rem;
        font-size: .88rem;
    }
    .btn-back{
        background: linear-gradient(135deg, var(--merah), var(--merah-tua));
        border: none;
        color: #fff;
        font-weight: 600;
        padding: .75rem 1.75rem;
        border-radius: .6rem;
        text-decoration: none;
        display: inline-block;
        transition: transform .15s ease;
    }
    .btn-back:hover{
        color: #fff;
        text-decoration: none;
        transform: translateY(-2px);
    }
    .expired-footer{
        font-size: .75rem;
        color: #aaa;
        padding-bottom: 1.5rem;
    }
</style>

<body class="bg-expired">
@include('sweetalert::alert')

<div class="expired-card">
    <div class="expired-header">
        <img src="{{ asset('img/chutex.svg') }}" alt="Chutex">
        <div class="expired-icon">⏳</div>
        <h5 class="mb-0"><b>PT. Chutex International Indonesia</b></h5>
    </div>

    <div class="expired-body">
        <h1>Event Sudah Berakhir</h1>

        @isset($event)
            <div class="event-name">
                <i class="fas fa-calendar-times mr-1"></i> {{ $event->nama_event }}
            </div>
        @endisset

        <p>
            Mohon maaf, undangan atau halaman scan untuk event ini
            sudah tidak aktif / berakhir. Silakan hubungi panitia
            apabila Anda merasa ini adalah suatu kekeliruan.
        </p>

        <a href="{{ url('/') }}" class="btn-back">
            <i class="fas fa-home mr-1"></i> Kembali ke Beranda
        </a>
    </div>

    <div class="expired-footer">
        PT. Chutex International Indonesia &copy; {{ date('Y') }}
    </div>
</div>

@include('layout.footerscript')
</body>
</html>
