<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transaksi Kantin — PT Chutex International Indonesia</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; }
        .canteen-card { border: none; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(0,0,0,.275); overflow: hidden; }
        .scanner-guide-box { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 220px; height: 260px; border: 2px dashed rgba(255,255,255,.7); border-radius: 50%; pointer-events: none; box-shadow: 0 0 0 9999px rgba(0,0,0,.35); transition: all .3s ease; }
        .scanner-guide-box.liveness-ok { border-color: #1cc88a; box-shadow: 0 0 20px #1cc88a, 0 0 0 9999px rgba(0,0,0,.25); }
        .canteen-choice { cursor: pointer; border: 2px solid #e3e6f0; border-radius: .75rem; padding: 1rem; text-align: center; transition: all .15s; }
        .canteen-choice:hover { border-color: #4e73df; }
        .canteen-choice.selected { border-color: #4e73df; background: rgba(78,115,223,.08); box-shadow: 0 .25rem .75rem rgba(0,0,0,.08); }
        .receipt { background: #fff; border: 1px dashed #b7b9cc; border-radius: .75rem; padding: 1rem 1.25rem; }
        .receipt hr { border-top: 1px dashed #b7b9cc; }
        .receipt .rowline { display: flex; justify-content: space-between; gap: 1rem; }
        .receipt .rowline span:first-child { color: #6b6f82; font-size: .85rem; }
        .receipt .rowline span:last-child { font-weight: 600; text-align: right; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card canteen-card bg-white">
                <div class="card-header bg-gradient-primary text-white text-center py-3">
                    <h5 class="font-weight-bold mb-0"><i class="fas fa-utensils mr-2"></i> Transaksi Kantin</h5>
                    <small>PT Chutex International Indonesia</small>
                </div>
                <div class="card-body p-4">

                    {{-- Window saat ini --}}
                    @if($insideWindow && $currentWindow)
                        <div class="alert alert-success text-center mb-3">
                            <i class="fas fa-clock mr-1"></i> Window aktif: <strong>{{ $currentWindow->name }} ({{ $currentWindow->time_start }} – {{ $currentWindow->time_end }})</strong>
                        </div>
                    @else
                        <div class="alert alert-warning text-center mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Di luar window jam istirahat.
                            <div class="small">Window tersedia:
                                @forelse($windows as $w)
                                    <span class="badge badge-light text-dark">{{ $w->name }} {{ $w->time_start }}–{{ $w->time_end }}</span>
                                @empty
                                    <em>belum dikonfigurasi admin</em>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- STEP 1: Pilih kantin --}}
                    <div id="stepCanteen">
                        <h6 class="font-weight-bold text-gray-800 mb-2">1. Pilih Kantin</h6>
                        <div class="row">
                            @forelse($canteens as $c)
                            <div class="col-6">
                                <div class="canteen-choice" data-canteen-id="{{ $c->canteen_id }}" data-canteen-name="{{ $c->name }}">
                                    <div class="mb-1"><i class="fas fa-store" style="font-size:28px; color:#4e73df;"></i></div>
                                    <strong>Kantin {{ $c->canteen_id }}</strong>
                                    <div class="small text-muted">{{ $c->name }}</div>
                                    <div class="small text-muted">{{ $c->location }}</div>
                                    <div class="small text-muted">Kontak: {{ $c->contact_person ?: $c->pic_name }}{{ ($c->contact_phone ?: $c->pic_phone) ? ' / ' . ($c->contact_phone ?: $c->pic_phone) : '' }}</div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12 alert alert-danger text-center">Data kantin belum dikonfigurasi admin.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- STEP 2: Input NPK --}}
                    <div id="stepNpk" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2">2. Masukkan NPK</h6>
                        <div class="alert alert-info small py-2">Kantin dipilih: <strong id="chosenCanteenLabel">-</strong></div>
                        <form id="npkForm" autocomplete="off" novalidate>
                            @csrf
                            <div class="form-group">
                                <label class="sr-only">NPK</label>
                                <input type="text" id="npkInput" class="form-control form-control-lg text-center"
                                       placeholder="Contoh: C-00827" autocapitalize="characters" autocomplete="off">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block" id="btnValidNpk">
                                <i class="fas fa-check mr-1"></i> Lanjut ke Scan Wajah
                            </button>
                        </form>
                        <button class="btn btn-link btn-sm mt-2" id="btnBackToCanteen"><i class="fas fa-arrow-left"></i> Ganti kantin</button>
                    </div>

                    {{-- STEP 3: Scan wajah --}}
                    <div id="stepFace" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2">3. Scan Wajah</h6>
                        <div class="alert alert-info small py-2">Kantin: <strong id="faceCanteenLabel">-</strong> &nbsp;|&nbsp; NPK: <strong id="faceNpkLabel">-</strong></div>

                        @include('canteen_transaction._liveness', ['livenessEnabled' => $livenessEnabled])

                        <div class="text-center mb-2">
                            <span class="badge badge-info" id="livenessBadge">Liveness: {{ $livenessEnabled ? 'ON' : 'OFF' }}</span>
                            <span class="badge badge-secondary" id="gpsBadge">GPS: {{ $setting->require_geomap ? 'Wajib' : 'Opsional' }}</span>
                        </div>

                        <div class="position-relative mx-auto" style="max-width:360px;">
                            <video id="canteenVideo" autoplay playsinline class="w-100 rounded bg-dark" style="min-height:260px;"></video>
                            <div id="cameraPlaceholder" class="text-center text-white py-4" style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:rgba(0,0,0,.55);">
                                <div style="font-size:28px;"><i class="fas fa-video-slash"></i></div>
                                <div class="small font-weight-bold mt-1" id="placeholderTitle">Kamera belum aktif</div>
                                <div class="small text-white-50 px-3" id="placeholderDesc">Menunggu izin kamera…</div>
                            </div>
                            <div class="scanner-guide-box" id="scannerGuide"></div>
                        </div>
                        <div id="faceStatus" class="text-center small text-muted mt-2"></div>

                        <div class="d-flex mt-3">
                            <button class="btn btn-secondary flex-fill mr-2" id="btnCameraToggle" type="button"><i class="fas fa-camera mr-1"></i> Nyalakan Kamera</button>
                            <button class="btn btn-success flex-fill" id="btnScanCapture" type="button" disabled><i class="fas fa-face mr-1"></i> Scan &amp; Simpan</button>
                        </div>
                        <div class="small text-muted mt-2 text-center" id="geoInfo">GPS: <span id="geoText">menunggu izin lokasi…</span></div>
                        <button class="btn btn-link btn-sm mt-2" id="btnBackToNpk"><i class="fas fa-arrow-left"></i> Kembali</button>
                    </div>

                    {{-- STEP 4: Receipt / invoice --}}
                    <div id="stepReceipt" class="d-none">
                        <h6 class="font-weight-bold text-gray-800 mb-2 text-center"><i class="fas fa-receipt mr-1"></i> Bukti Scan Kantin</h6>
                        <p class="small text-muted text-center">Tunjukkan bukti ini ke pihak kantin, seperti bukti bayar QRIS.</p>
                        <div class="receipt" id="receiptBox"></div>
                        <button class="btn btn-primary btn-block mt-3" id="btnDone"><i class="fas fa-check mr-1"></i> Selesai</button>
                    </div>

                    <div id="globalError" class="alert alert-danger d-none mt-3"></div>

                </div>
            </div>
            <div class="text-center mt-3"><a href="/" class="text-white small"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Beranda</a></div>
        </div>
    </div>
</div>

<script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
(function(){
'use strict';
let selectedCanteenId = null;
let selectedCanteenName = '';
let validatedNpk = '';
let cameraStream = null;
let faceLoaded = false;
let geoCoords = { latitude: null, longitude: null, accuracy: null };

const $ = id => document.getElementById(id);
const csrf = document.querySelector('meta[name=\"csrf-token\"]').content;

function showStep(id){
  ['stepCanteen','stepNpk','stepFace','stepReceipt'].forEach(s=>$(s).classList.add('d-none'));
  $(id).classList.remove('d-none');
}
function setError(msg){
  const el = $('globalError');
  if(!msg){ el.classList.add('d-none'); el.textContent=''; return; }
  el.textContent = msg; el.classList.remove('d-none');
}
function setStatus(html){ $('faceStatus').innerHTML = html; }

// Pilih kantin
document.querySelectorAll('.canteen-choice').forEach(el=>{
  el.addEventListener('click', ()=>{
    document.querySelectorAll('.canteen-choice').forEach(x=>x.classList.remove('selected'));
    el.classList.add('selected');
    selectedCanteenId = el.dataset.canteenId;
    selectedCanteenName = el.dataset.canteenName;
    $('chosenCanteenLabel').textContent = 'Kantin ' + selectedCanteenId + ' — ' + selectedCanteenName;
    setError(null);
    showStep('stepNpk');
    setTimeout(()=>$('npkInput').focus(), 100);
  });
});
$('btnBackToCanteen').addEventListener('click', ()=>{ showStep('stepCanteen'); });

// Validasi NPK
$('npkForm').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const npk = $('npkInput').value.trim().toUpperCase();
  if(!npk){ setError('NPK wajib diisi.'); return; }
  if(!selectedCanteenId){ setError('Pilih Kantin 1 atau Kantin 2 terlebih dahulu.'); showStep('stepCanteen'); return; }
  setError(null);
  $('btnValidNpk').disabled = true;
  $('btnValidNpk').innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\"></i> Memvalidasi…';
  try {
    const res = await fetch(@json(route('canteen-transaction.validate-npk')), {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, 'Accept':'application/json' },
      body: JSON.stringify({ npk })
    });
    const data = await res.json().catch(()=>({}));
    if(!res.ok || !data.success){
      setError(data.message || 'NPK tidak valid.');
      return;
    }
    validatedNpk = data.npk || npk;
    $('faceCanteenLabel').textContent = 'Kantin ' + selectedCanteenId + ' — ' + selectedCanteenName;
    $('faceNpkLabel').textContent = validatedNpk;
    showStep('stepFace');
    requestGeo();
    // Kamera nyala otomatis setelah NPK valid (sejajar FaceAttendance: kamera nyala saat NPK valid).
    await loadFaceModels();
    await startCamera();
    if (window.Swal) { Swal.fire({ icon: 'success', title: 'NPK valid', text: 'Silakan posisikan wajah di kamera.', confirmButtonText: 'OK', timer: 3000, timerProgressBar: true }); }
  } catch(err){
    setError('Gagal memvalidasi NPK. Periksa koneksi lalu coba lagi.');
  } finally {
    $('btnValidNpk').disabled = false;
    $('btnValidNpk').innerHTML = '<i class=\"fas fa-check mr-1\"></i> Lanjut ke Scan Wajah';
  }
});
$('btnBackToNpk').addEventListener('click', ()=>{ stopCamera(); showStep('stepNpk'); });

// GPS
function requestGeo(){
  if(!navigator.geolocation){ $('geoText').textContent='browser tidak mendukung GPS'; return; }
  navigator.geolocation.getCurrentPosition((pos)=>{
    geoCoords.latitude = pos.coords.latitude;
    geoCoords.longitude = pos.coords.longitude;
    geoCoords.accuracy = pos.coords.accuracy;
    $('geoText').textContent = geoCoords.latitude.toFixed(6) + ', ' + geoCoords.longitude.toFixed(6) + ' (akurasi ±' + Math.round(pos.coords.accuracy) + 'm)';
  }, ()=>{
    $('geoText').textContent = 'izin lokasi ditolak / tidak tersedia';
  }, { enableHighAccuracy:true, timeout:10000 });
}
requestGeo();

// Kamera
async function loadFaceModels(){
  if(typeof faceapi === 'undefined'){
    setStatus('Library wajah (face-api) gagal dimuat.');
    return;
  }
  const url = '{{ asset("vendor-face-api/model") }}';
  try {
    await Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(url),
      faceapi.nets.faceLandmark68TinyNet.loadFromUri(url),
      faceapi.nets.faceRecognitionNet.loadFromUri(url),
    ]);
    faceLoaded = true;
    setStatus('Model wajah siap. Nyalakan kamera untuk mulai.');
  } catch(e){
    setStatus('Model wajah belum termuat. Muat ulang halaman.');
  }
}

async function startCamera(){
  if(!faceLoaded) await loadFaceModels();
  const video = $('canteenVideo');
  const ph = $('cameraPlaceholder'), phTitle = $('placeholderTitle'), phDesc = $('placeholderDesc');
  if (video && video.srcObject && video.srcObject.active) return;
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    console.error('[Canteen] getUserMedia tidak didukung.');
    if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display','flex','important'); }
    if (phTitle) phTitle.innerText = 'Kamera Tidak Didukung Browser';
    if (phDesc) phDesc.innerText = 'Browser ini tidak mendukung akses kamera. Kamera HANYA bisa aktif lewat HTTPS atau localhost. Gunakan Chrome / Edge terbaru dan pastikan halaman diakses via HTTPS.';
    return;
  }
  let camTimeout = null;
  try {
    cameraStream = await new Promise((resolve, reject) => {
      camTimeout = setTimeout(() => { const e = new Error('Izin kamera belum diberikan dalam 20 detik.'); e.name = 'IZIN_KAMERA_TIMEOUT'; reject(e); }, 20000);
      const tryConstraints = (constraints, fallback) => {
        navigator.mediaDevices.getUserMedia(constraints).then(resolve, (err) => {
          if (fallback && fallback.length) { tryConstraints(fallback[0], fallback.slice(1)); } else { reject(err); }
        });
      };
      tryConstraints(
        { video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }, audio: false },
        [ { video: { facingMode: 'user' }, audio: false }, { video: true, audio: false } ]
      );
    });
    clearTimeout(camTimeout);
    video.srcObject = cameraStream;
    try { await video.play(); } catch(e) { if (e.name !== 'AbortError') console.warn('Video play warning:', e); }
    if (ph) { ph.classList.add('d-none'); ph.style.setProperty('display','none','important'); }
    $('btnScanCapture').disabled = false;
    $('btnCameraToggle').innerHTML = '<i class=\"fas fa-camera mr-1\"></i> Matikan Kamera';
    setStatus('Posisikan wajah di dalam lingkaran, lalu tekan <b>Scan &amp; Simpan</b>.');
  } catch(err){
    clearTimeout(camTimeout);
    console.error('Camera error:', err);
    if (ph) { ph.classList.remove('d-none'); ph.style.setProperty('display','flex','important'); }
    var name = (err && err.name) ? err.name : '';
    if (phTitle && phDesc) {
      if (name === 'IZIN_KAMERA_TIMEOUT') { phTitle.innerText = 'Menunggu Izin Kamera'; phDesc.innerText = 'Izin kamera belum diberikan. Klik ikon kamera di address bar, pilih "Izinkan", lalu coba lagi.'; }
      else if (name === 'NotAllowedError' || name === 'SecurityError') { phTitle.innerText = 'Izin Kamera Ditolak'; phDesc.innerText = 'Klik ikon kamera di address bar browser, pilih "Izinkan", lalu coba lagi.'; }
      else if (name === 'NotFoundError' || name === 'OverconstrainedError') { phTitle.innerText = 'Kamera Tidak Ditemukan'; phDesc.innerText = 'Tidak ada kamera yang terdeteksi pada perangkat ini.'; }
      else if (name === 'NotReadableError') { phTitle.innerText = 'Kamera Sedang Digunakan'; phDesc.innerText = 'Kamera sedang digunakan aplikasi lain. Tutup aplikasi tersebut lalu coba lagi.'; }
      else { phTitle.innerText = 'Kamera Tidak Dapat Diakses'; phDesc.innerText = (err && err.message) ? err.message : String(err); }
    }
    setStatus('Kamera tidak dapat diakses. Beri izin kamera di browser.');
  }
}
function stopCamera(){
  if(cameraStream){ cameraStream.getTracks().forEach(t=>t.stop()); cameraStream = null; }
  const ph2 = $('cameraPlaceholder'); if (ph2) { ph2.classList.remove('d-none'); ph2.style.setProperty('display','flex','important'); }
  $('btnScanCapture').disabled = true;
  $('btnCameraToggle').innerHTML = '<i class=\"fas fa-camera mr-1\"></i> Nyalakan Kamera';
}
$('btnCameraToggle').addEventListener('click', ()=>{ cameraStream ? stopCamera() : startCamera(); });

// Liveness sederhana: menunggu deteksi wajah stabil beberapa frame (tetap 2D, bukan anti-spoofing penuh).
let stableFrames = 0;
setInterval(async ()=>{
  if(!cameraStream || !faceLoaded || typeof faceapi === 'undefined') return;
  const video = $('canteenVideo');
  if(!video || video.readyState < 2) return;
  try {
    const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true);
    if(det){
      stableFrames++;
      $('scannerGuide').classList.add('liveness-ok');
      if(stableFrames >= 3){
        setStatus('Wajah terdeteksi stabil. Silakan tekan <b>Scan &amp; Simpan</b>.');
      }
    } else {
      stableFrames = 0;
      $('scannerGuide').classList.remove('liveness-ok');
    }
  } catch(e){}
}, 600);

$('btnScanCapture').addEventListener('click', async ()=>{
  if(!cameraStream){ setStatus('Nyalakan kamera terlebih dahulu.'); return; }
  setError(null);
  $('btnScanCapture').disabled = true;
  $('btnScanCapture').innerHTML = '<i class=\"fas fa-spinner fa-spin mr-1\"></i> Memproses…';
  setStatus('Mendeteksi wajah &amp; membuat descriptor…');
  try {
    const video = $('canteenVideo');
    const det = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks(true).withFaceDescriptor();
    if(!det){
      setStatus('Wajah tidak terdeteksi. Posisi wajah lebih terang &amp; dekat ke kamera.');
      return;
    }
    const descriptorArray = Array.from(det.descriptor);
    const payload = {
      descriptor: descriptorArray,
      npk: validatedNpk,
      canteen_id: selectedCanteenId,
      latitude: geoCoords.latitude,
      longitude: geoCoords.longitude,
    };
    const res = await fetch(@json(route('canteen-transaction.store')), {
      method:'POST',
      headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, 'Accept':'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(()=>({}));
    if(!res.ok || !data.success){
      setError(data.message || 'Transaksi gagal.');
      setStatus('Scan gagal. Perbaiki posisi wajah lalu coba lagi.');
      return;
    }
    stopCamera();
    renderReceipt(data.invoice);
    showStep('stepReceipt');
    if(window.Swal) Swal.fire({ icon:'success', title:'Berhasil!', text: data.message, confirmButtonText:'Lihat Bukti' });
  } catch(err){
    setError('Terjadi kesalahan jaringan. Silakan coba lagi.');
  } finally {
    $('btnScanCapture').disabled = false;
    $('btnScanCapture').innerHTML = '<i class=\"fas fa-face mr-1\"></i> Scan &amp; Simpan';
  }
});

function esc(s){ return String(s ?? '').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderReceipt(inv){
  const inside = inv.inside_geomap === null || inv.inside_geomap === undefined ? '-' : (inv.inside_geomap ? 'Ya' : 'Tidak');
  $('receiptBox').innerHTML = `
    <div class="text-center mb-2">
      <div class="font-weight-bold">PT Chutex International Indonesia</div>
      <div class="small text-muted">Bukti transaksi kantin — tunjukkan ke pihak kantin</div>
    </div>
    <hr>
    <div class="rowline"><span>No. Bukti</span><span>${esc(inv.no)}</span></div>
    <div class="rowline"><span>Kantin</span><span>Kantin ${esc(inv.canteen_id)} — ${esc(inv.canteen_name)}</span></div>
    <div class="rowline"><span>Lokasi</span><span>${esc(inv.canteen_location || '-')}</span></div>
    <div class="rowline"><span>Kontak</span><span>${esc(inv.contact_person || '-')}${inv.contact_phone ? ' / ' + esc(inv.contact_phone) : ''}</span></div>
    <hr>
    <div class="rowline"><span>NPK</span><span>${esc(inv.npk)}</span></div>
    <div class="rowline"><span>Nama</span><span>${esc(inv.name)}</span></div>
    <div class="rowline"><span>Dept</span><span>${esc(inv.dept || '-')}</span></div>
    <div class="rowline"><span>Window</span><span>${esc(inv.window_name)}</span></div>
    <div class="rowline"><span>Tanggal</span><span>${esc(inv.date)}</span></div>
    <div class="rowline"><span>Jam</span><span>${esc(inv.time)}</span></div>
    <div class="rowline"><span>Area Geomap</span><span>${esc(inv.location_name || '-')} (${inside})</span></div>
    ${inv.latitude !== null && inv.latitude !== undefined ? `<div class="rowline"><span>Koordinat</span><span>${Number(inv.latitude).toFixed(6)}, ${Number(inv.longitude).toFixed(6)}</span></div>` : ''}
    <hr>
    <div class="text-center small text-muted">Terima kasih — selamat makan!</div>
  `;
}

$('btnDone').addEventListener('click', ()=>{
  stopCamera();
  validatedNpk='';
  selectedCanteenId=null;
  showStep('stepCanteen');
});
})();
</script>
</body>
</html>
