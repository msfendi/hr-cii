@include('layout.header')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root{
        --merah: #C8102E;
        --merah-tua: #7a0000;
        --hijau: #165B33;
        --hijau-tua: #0B3D24;
        --putih: #ffffff;
        --gold: #FFD700;
    }
    html,body{
        font-family: 'Poppins', sans-serif;
        background: #0B3D24;
        overflow-x: hidden;
    }
    .font-display{ font-family:'Bebas Neue', sans-serif; letter-spacing: 2px; }

    /* ============== COVER ============== */
    #cover{
        position: fixed;
        inset: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color:#fff;
        background:
            radial-gradient(circle at 50% 20%, rgba(255,215,0,.18), transparent 55%),
            linear-gradient(160deg, var(--merah) 0%, var(--hijau-tua) 60%, #021b10 100%);
        padding: 2rem 1.5rem;
        transition: opacity .6s ease, visibility .6s ease;
    }
    #cover.hide{ opacity:0; visibility:hidden; pointer-events:none; }
    #cover h4{ opacity:.85; letter-spacing:3px; text-transform:uppercase; font-size:.85rem; }
    #cover h1{ font-size: 3rem; margin: .25rem 0 .5rem; }
    #cover .guest-box{
        border: 1.5px solid rgba(255,215,0,.6);
        border-radius: .75rem;
        padding: .9rem 1.5rem;
        margin: 1.25rem 0 2rem;
        background: rgba(255,255,255,.06);
        backdrop-filter: blur(2px);
    }
    #cover .guest-box small{ opacity:.75; display:block; letter-spacing:1px; }
    #cover .guest-box .nama{ font-size:1.25rem; font-weight:700; color: var(--gold); }
    #btn-buka{
        background: linear-gradient(135deg, var(--gold), #e6b800);
        border:none;
        color:#5c1a00;
        font-weight:700;
        padding:.85rem 2.2rem;
        border-radius: 3rem;
        box-shadow: 0 8px 25px rgba(0,0,0,.35);
        animation: pulse-btn 2s ease-in-out infinite;
    }
    @keyframes pulse-btn{
        0%,100%{ transform: scale(1); }
        50%{ transform: scale(1.05); }
    }
    .garuda-watermark{
        position:absolute;
        font-size: 12rem;
        opacity:.06;
        bottom:-2rem;
        pointer-events:none;
    }

    /* ============== BUNTING (bendera hias) ============== */
    .bunting{
        display:flex;
        justify-content:center;
        gap: 4px;
        padding-top: 8px;
        overflow:hidden;
        position: relative;
        z-index: 5;
    }
    .bunting span{
        width: 0; height: 0;
        border-left: 14px solid transparent;
        border-right: 14px solid transparent;
        border-top: 22px solid var(--putih);
        animation: sway 2.4s ease-in-out infinite;
        transform-origin: top center;
    }
    .bunting span:nth-child(odd){ border-top-color: var(--merah); animation-delay: .2s; }
    .bunting span:nth-child(3n){ border-top-color: var(--gold); animation-delay:.4s; }
    .bunting span:nth-child(4n){ border-top-color: var(--hijau); animation-delay:.3s; }
    @keyframes sway{
        0%,100%{ transform: rotate(-4deg); }
        50%{ transform: rotate(4deg); }
    }

    /* ============== MAIN CONTENT ============== */
    #main{ display:none; }

    /* Hero: countdown ada DI DALAM hero, jadi butuh padding-bottom yang
       cukup besar supaya kartu info putih di bawahnya tidak pernah
       menabrak angka countdown, walau di layar kecil sekalipun. */
    .hero{
        background:
            radial-gradient(circle at 15% 10%, rgba(255,215,0,.12), transparent 50%),
            linear-gradient(160deg, var(--merah) 0%, var(--hijau-tua) 100%);
        color:#fff;
        text-align:center;
        padding: 2.5rem 1.25rem 3rem;
    }
    .hero .eyebrow{ letter-spacing:3px; font-size:.75rem; opacity:.8; text-transform:uppercase; }
    .hero h1{ font-size: 2.6rem; margin: .4rem 0 .2rem; text-shadow: 0 4px 18px rgba(0,0,0,.35); }
    .hero .sub{ opacity:.9; }
    .flag-wave{ display:inline-block; animation: wave 1.4s ease-in-out infinite; transform-origin: 70% 70%; }
    @keyframes wave{
        0%,100%{ transform: rotate(0deg); }
        25%{ transform: rotate(14deg); }
        75%{ transform: rotate(-8deg); }
    }

    /* countdown */
    .countdown{
        display:flex;
        justify-content:center;
        gap:.6rem;
        margin: 1.75rem 0 .75rem;
        flex-wrap: wrap;
    }
    .countdown .box{
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,215,0,.45);
        border-radius: .6rem;
        padding: .6rem .4rem;
        min-width: 70px;
        backdrop-filter: blur(3px);
    }
    .countdown .box .num{
        font-family:'Bebas Neue', sans-serif;
        font-size: 2rem;
        color: var(--gold);
        line-height:1;
    }
    .countdown .box .lbl{ font-size:.65rem; letter-spacing:1px; text-transform:uppercase; opacity:.85; }
    .countdown-caption{ opacity:.8; margin-bottom: 0; }

    /* info card — diberi jarak POSITIF dari hero, bukan overlap negatif,
       supaya tidak pernah menimpa countdown di atasnya. */
    .card-info-wrap{
        padding: 0 1rem;
        margin-top: 1.75rem;
    }
    .card-info{
        background:#fff;
        border-radius: 1rem;
        box-shadow: 0 15px 40px rgba(0,0,0,.22);
        max-width: 480px;
        margin: 0 auto;
        overflow:hidden;
    }
    .card-info .row-item{
        display:flex;
        gap:.85rem;
        align-items:flex-start;
        padding: .9rem 1.25rem;
        border-bottom: 1px dashed #eee;
    }
    .card-info .row-item:last-child{ border-bottom:none; }
    .card-info .row-item i{ color: var(--hijau-tua); font-size:1.1rem; margin-top:.2rem; width: 22px; text-align:center; }
    .card-info .row-item .lbl{ font-size:.72rem; text-transform:uppercase; letter-spacing:.5px; color:#999; }
    .card-info .row-item .val{ font-weight:600; color:#333; }

    /* doorprize section */
    .doorprize-section{
        background: linear-gradient(180deg, #fff8e6 0%, #fff 100%);
        text-align:center;
    }
    .gift-box{
        font-size: 5rem;
        display:inline-block;
        animation: gift-bounce 1.6s ease-in-out infinite;
        filter: drop-shadow(0 10px 15px rgba(0,0,0,.15));
    }
    @keyframes gift-bounce{
        0%,100%{ transform: translateY(0) rotate(-3deg); }
        50%{ transform: translateY(-14px) rotate(3deg); }
    }
    .sparkle{
        position:absolute;
        color: var(--gold);
        animation: twinkle 1.8s ease-in-out infinite;
    }
    @keyframes twinkle{
        0%,100%{ opacity:.2; transform: scale(.7); }
        50%{ opacity:1; transform: scale(1.15); }
    }
    .doorprize-badges span{
        display:inline-block;
        background: linear-gradient(135deg, var(--merah), var(--hijau-tua));
        color:#fff;
        border-radius: 2rem;
        padding:.4rem 1rem;
        font-size:.78rem;
        font-weight:600;
        margin: .25rem;
    }

    /* RSVP */
    .rsvp-section{ background: #fdfdfd; }
    .rsvp-choice{ display:flex; gap:.75rem; flex-wrap:wrap; }
    .rsvp-choice label{
        flex:1 1 150px;
        border: 2px solid #e5e5e5;
        border-radius: .85rem;
        padding: 1rem .75rem;
        text-align:center;
        cursor:pointer;
        transition: all .15s ease;
        margin:0;
    }
    .rsvp-choice input{ display:none; }
    .rsvp-choice label .ico{ font-size:1.8rem; display:block; margin-bottom:.35rem; }
    .rsvp-choice input:checked + label{
        border-color: var(--merah);
        background: rgba(200,16,46,.06);
        box-shadow: 0 4px 14px rgba(200,16,46,.15);
    }
    .rsvp-choice input:checked + label.hadir{ border-color:#1f9d55; background: rgba(31,157,85,.07); }

    .btn-rsvp-submit{
        background: linear-gradient(135deg, var(--merah), var(--hijau-tua));
        border:none;
        color:#fff;
        font-weight:700;
        padding:.85rem;
        border-radius:.6rem;
        width:100%;
    }

    .thanks-box{ text-align:center; padding: 2rem 1rem; }
    .thanks-box .big-ico{ font-size:3.5rem; }

    footer.merdeka-footer{
        background: var(--hijau-tua);
        color: rgba(255,255,255,.75);
        text-align:center;
        padding: 1.5rem;
        font-size:.8rem;
    }

    .confetti-piece{
        position: fixed;
        top: -10px;
        z-index: 2000;
        pointer-events:none;
        border-radius: 2px;
    }

    @media (max-width: 420px){
        .hero h1{ font-size: 2.1rem; }
        .countdown .box{ min-width: 60px; padding:.5rem .3rem; }
        .countdown .box .num{ font-size:1.5rem; }
    }
</style>

<body>
@include('sweetalert::alert')

{{-- =============== COVER =============== --}}
<div id="cover">
    <div class="garuda-watermark">🎄</div>
    <h4>Undangan Resmi</h4>
    <h1 class="font-display">SELAMAT NATAL<br>&amp; TAHUN BARU</h1>
    <p class="mb-0" style="opacity:.85;">PT. Chutex International Indonesia</p>

    <div class="guest-box">
        <small>Kepada Yth. Bapak/Ibu/Saudara/i</small>
        <div class="nama">{{ $nama }}</div>
        <small>NPK {{ $npk }} &bull; {{ $departemen }}</small>
    </div>

    <button id="btn-buka" onclick="bukaUndangan()">
        <i class="fas fa-gift mr-2"></i> Buka Undangan
    </button>
</div>

{{-- =============== MAIN CONTENT =============== --}}
<div id="main">

    <div class="bunting">
        @for ($i = 0; $i < 14; $i++) <span></span> @endfor
    </div>

    <section class="hero">
        <div class="eyebrow">🎄 Selamat Natal &amp; Tahun Baru</div>
        <h1 class="font-display"><span class="flag-wave">🎄</span> {{ $event->nama_event }}</h1>
        <p class="sub mb-0">PT. Chutex International Indonesia</p>

        <div class="countdown" id="countdown">
            <div class="box"><div class="num" id="cd-days">00</div><div class="lbl">Hari</div></div>
            <div class="box"><div class="num" id="cd-hours">00</div><div class="lbl">Jam</div></div>
            <div class="box"><div class="num" id="cd-mins">00</div><div class="lbl">Menit</div></div>
            <div class="box"><div class="num" id="cd-secs">00</div><div class="lbl">Detik</div></div>
        </div>
        <p class="small countdown-caption">menuju perayaan Natal &amp; Tahun Baru</p>
    </section>

    <div class="card-info-wrap">
        <div class="card-info">
            <div class="row-item">
                <i class="fas fa-calendar-day"></i>
                <div><div class="lbl">Tanggal</div><div class="val">{{ $event->tanggal_display }}</div></div>
            </div>
            <div class="row-item">
                <i class="fas fa-clock"></i>
                <div><div class="lbl">Waktu</div><div class="val">{{ $event->waktu_event }}</div></div>
            </div>
            <div class="row-item">
                <i class="fas fa-map-marker-alt"></i>
                <div><div class="lbl">Lokasi</div><div class="val">{{ $event->lokasi_event }}</div></div>
            </div>
            @if ($event->dress_code)
                <div class="row-item">
                    <i class="fas fa-tshirt"></i>
                    <div><div class="lbl">Dress Code</div><div class="val">{{ $event->dress_code }}</div></div>
                </div>
            @endif
            @if ($event->detail_event)
                <div class="row-item">
                    <i class="fas fa-info-circle"></i>
                    <div><div class="lbl">Info Tambahan</div><div class="val" style="font-weight:400;">{{ $event->detail_event }}</div></div>
                </div>
            @endif
        </div>
    </div>
    <br>
    {{-- =============== DOORPRIZE =============== --}}
    <section class="section doorprize-section" style="padding: 3rem 1.25rem;">
        <div style="position:relative; display:inline-block;">
            <span class="sparkle" style="top:-10px; left:-25px; font-size:1.1rem;">✨</span>
            <span class="sparkle" style="top:5px; right:-30px; font-size:1.4rem; animation-delay:.6s;">✨</span>
            <span class="sparkle" style="bottom:-15px; left:10px; font-size:1rem; animation-delay:1.1s;">⭐</span>
            <div class="gift-box">🎁</div>
        </div>
        <h2 class="font-display mt-2" style="color: var(--merah); letter-spacing:1px;">ADA DOORPRIZE MENARIK!</h2>
        <p class="text-muted px-2" style="max-width:420px; margin:0 auto;">
            Jangan sampai terlewat! Khusus untuk yang hadir langsung di lokasi, disediakan
            <b>doorprize</b> spesial di penghujung acara. Semakin cepat konfirmasi hadir,
            semakin meriah kebersamaan kita merayakan Natal &amp; Tahun Baru 🎉
        </p>
        <div class="doorprize-badges mt-3">
            <span><i class="fas fa-gift mr-1"></i> Hadiah Menarik</span>
            <span><i class="fas fa-users mr-1"></i> Wajib Hadir</span>
            <span><i class="fas fa-star mr-1"></i> Kejutan Spesial</span>
        </div>
    </section>

    {{-- =============== RSVP FORM / THANKS =============== --}}
    <section class="section rsvp-section" id="rsvp-section" style="padding: 3rem 1.25rem;">
        @if ($invitation && $invitation->is_confirmed)
            <div class="thanks-box" id="thanks-box">
                <div class="big-ico">{{ $invitation->is_hadir ? '🎉' : '🙏' }}</div>
                <h4 class="font-display" style="color:var(--merah);">
                    {{ $invitation->is_hadir ? 'TERIMA KASIH, SAMPAI JUMPA!' : 'TERIMA KASIH ATAS KONFIRMASINYA' }}
                </h4>
                <p class="text-muted">
                    Anda ({{ $invitation->nama }}) telah mengonfirmasi:
                    <br>
                    <span class="badge {{ $invitation->is_hadir ? 'badge-success' : 'badge-secondary' }} p-2 mt-2">
                        {{ $invitation->is_hadir ? 'BISA HADIR' : 'TIDAK BISA HADIR' }}
                    </span>
                </p>
                <button class="btn btn-outline-danger btn-sm mt-2" onclick="tampilkanForm()">
                    <i class="fas fa-pen mr-1"></i> Ubah Jawaban
                </button>
            </div>
        @endif

        <div id="form-wrapper" @if ($invitation && $invitation->is_confirmed) style="display:none;" @endif>
            <div class="text-center mb-3">
                <h4 class="font-display" style="color:var(--merah);">KONFIRMASI KEHADIRAN</h4>
                <p class="text-muted small mb-0">
                    {{ $nama }} &bull; NPK {{ $npk }} &bull; {{ $departemen }}
                </p>
            </div>

            <form id="rsvp-form" style="max-width:420px; margin:0 auto;">
                @csrf
                <div class="rsvp-choice mb-3">
                    <input type="radio" name="status" id="opt-hadir" value="hadir" required
                        {{ optional($invitation)->status === 'hadir' ? 'checked' : '' }}>
                    <label for="opt-hadir" class="hadir">
                        <span class="ico">🎉</span>
                        <b>Bisa Hadir</b>
                        <div class="small text-muted">Saya akan datang!</div>
                    </label>

                    <input type="radio" name="status" id="opt-tidak" value="tidak_hadir" required
                        {{ optional($invitation)->status === 'tidak_hadir' ? 'checked' : '' }}>
                    <label for="opt-tidak">
                        <span class="ico">🙏</span>
                        <b>Tidak Bisa Hadir</b>
                        <div class="small text-muted">Mohon maaf berhalangan</div>
                    </label>
                </div>

                <div class="form-group">
                    <label class="small text-muted">Ucapan / Doa untuk Natal &amp; Tahun Baru (opsional)</label>
                    <textarea name="ucapan" class="form-control" rows="3"
                        placeholder="Tuliskan ucapan atau harapan Anda...">{{ optional($invitation)->ucapan }}</textarea>
                </div>

                <button type="submit" class="btn-rsvp-submit" id="btn-submit-rsvp">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Konfirmasi
                </button>
            </form>
        </div>
    </section>

    <footer class="merdeka-footer">
        <div class="mb-1" style="font-size:1.4rem;">🎄🎆</div>
        SELAMAT NATAL &amp; TAHUN BARU!<br>
        PT. Chutex International Indonesia &copy; {{ date('Y') }}
    </footer>
</div>

@include('layout.footerscript')
</body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const EVENT_DATE = new Date("{{ \Carbon\Carbon::parse($event->countdown_target)->toIso8601String() }}");
const eventId = {{ $event->id }};

/* ---------------- COVER ---------------- */
function bukaUndangan(){
    document.getElementById('cover').classList.add('hide');
    document.getElementById('main').style.display = 'block';
    document.body.style.overflow = 'auto';
    burstConfetti(60);
}
document.body.style.overflow = 'hidden';

/* ---------------- COUNTDOWN ---------------- */
function updateCountdown(){
    const now = new Date();
    let diff = EVENT_DATE - now;

    if (diff < 0) diff = 0;

    const d = Math.floor(diff / (1000 * 60 * 60 * 24));
    const h = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const m = Math.floor((diff / (1000 * 60)) % 60);
    const s = Math.floor((diff / 1000) % 60);

    document.getElementById('cd-days').textContent  = String(d).padStart(2, '0');
    document.getElementById('cd-hours').textContent = String(h).padStart(2, '0');
    document.getElementById('cd-mins').textContent  = String(m).padStart(2, '0');
    document.getElementById('cd-secs').textContent  = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);

/* ---------------- CONFETTI (merah, hijau, emas, putih) ---------------- */
function burstConfetti(amount = 40){
    const colors = ['#C8102E', '#165B33', '#FFD700', '#ffffff'];
    for (let i = 0; i < amount; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-piece';
        const size = 6 + Math.random() * 6;
        el.style.width = size + 'px';
        el.style.height = (size * 1.6) + 'px';
        el.style.left = Math.random() * 100 + 'vw';
        el.style.background = colors[Math.floor(Math.random() * colors.length)];
        el.style.opacity = .85;
        el.style.transform = `rotate(${Math.random() * 360}deg)`;
        document.body.appendChild(el);

        const duration = 2200 + Math.random() * 1800;
        const drift = (Math.random() - 0.5) * 200;

        el.animate([
            { transform: `translate(0,0) rotate(0deg)`, opacity: 1 },
            { transform: `translate(${drift}px, 100vh) rotate(${360 + Math.random()*360}deg)`, opacity: .9 }
        ], { duration, easing: 'ease-in' }).onfinish = () => el.remove();
    }
}

/* ---------------- RSVP FORM ---------------- */
function tampilkanForm(){
    document.getElementById('thanks-box').style.display = 'none';
    document.getElementById('form-wrapper').style.display = 'block';
    document.getElementById('form-wrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.getElementById('rsvp-form').addEventListener('submit', function(e){
    e.preventDefault();

    const btn = document.getElementById('btn-submit-rsvp');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengirim...';

    const formData = new FormData(this);

    fetch("{{ route('event-invitation.respond', ['event' => $event->id]) }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: formData,
    })
    .then(res => res.json().then(data => ({ ok: res.ok, data })))
    .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (!ok) {
            Swal.fire({ icon: 'error', title: 'Gagal', text: data.message || 'Terjadi kesalahan.' });
            return;
        }

        const isHadir = data.data.status === 'hadir';

        Swal.fire({
            icon: 'success',
            title: isHadir ? 'Sampai Jumpa! 🎉' : 'Terima Kasih 🙏',
            text: data.message,
        }).then(() => {
            if (isHadir) burstConfetti(90);
            document.getElementById('rsvp-section').innerHTML = `
                <div class="thanks-box">
                    <div class="big-ico">${isHadir ? '🎉' : '🙏'}</div>
                    <h4 class="font-display" style="color:var(--merah);">
                        ${isHadir ? 'TERIMA KASIH, SAMPAI JUMPA!' : 'TERIMA KASIH ATAS KONFIRMASINYA'}
                    </h4>
                    <p class="text-muted">
                        Jawaban Anda telah tersimpan sebagai
                        <span class="badge ${isHadir ? 'badge-success' : 'badge-secondary'} p-2">
                            ${isHadir ? 'BISA HADIR' : 'TIDAK BISA HADIR'}
                        </span>
                    </p>
                </div>
            `;
        });
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        Swal.fire({ icon: 'error', title: 'Gagal', text: 'Terjadi kesalahan jaringan, silakan coba lagi.' });
    });
});

setInterval(() => burstConfetti(12), 9000);
</script>
</html>
