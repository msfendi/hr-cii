<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <style>
      .face-video {
          width: 100%;
          height: 100%;
          object-fit: cover;
      }
      .face-thumb {
          width: 56px;
          height: 56px;
          object-fit: cover;
          border-radius: 8px;
          border: 1px solid #e3e6f0;
      }
      /* Kamera wajah persegi (1:1), bukan persegi panjang */
      .camera-box-md {
          width: 100%;
          max-width: 400px;
          aspect-ratio: 1 / 1;
          margin: 0 auto;
          background: #f8f9fc;
          border: 1px dashed #d1d3e2;
          border-radius: 12px;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: center;
      }
      .camera-box-md .face-video {
          width: 100%;
          height: 100%;
          object-fit: cover;
      }
  </style>
  <body id="page-top">
  @include('sweetalert::alert')
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          @include('layout.navbar')
          <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Manage Face Recognition</h1>
              <div class="d-flex gap-2">
                <span class="badge badge-{{ $settings->is_enabled ? 'success' : 'secondary' }} px-3 py-2">
                  Face Recognition: {{ $settings->is_enabled ? 'ON' : 'OFF' }}
                </span>
                <span class="badge badge-{{ $settings->is_qr_enabled ? 'info' : 'secondary' }} px-3 py-2 ml-2">
                  QR Login: {{ $settings->is_qr_enabled ? 'ON' : 'OFF' }}
                </span>
              </div>
            </div>

            {{-- ================= PENGATURAN GLOBAL ================= --}}
            <div class="card shadow mb-4 {{ $settings->is_enabled ? 'border-left-success' : 'border-left-secondary' }}">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                  <i class="fas fa-power-off mr-1"></i> Pengaturan Face Recognition
                </h6>
              </div>
              <div class="card-body">
                <form action="{{ route('face-recognition.settings') }}" method="POST" class="form-inline flex-wrap">
                  @csrf
                  @method('PATCH')

                  <input type="hidden" name="is_enabled" value="0">
                  <input type="hidden" name="is_qr_enabled" value="0">

                  <div class="custom-control custom-switch mr-4 mb-2">
                    <input type="checkbox" class="custom-control-input" id="is_enabled" name="is_enabled"
                      value="1" {{ $settings->is_enabled ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_enabled">
                      Aktifkan Face Recognition (login &amp; slip payroll)
                    </label>
                  </div>

                  <div class="custom-control custom-switch mr-4 mb-2">
                    <input type="checkbox" class="custom-control-input" id="is_qr_enabled" name="is_qr_enabled"
                      value="1" {{ $settings->is_qr_enabled ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_qr_enabled">
                      Aktifkan QR Login (login utama, slip gaji, THR, pengajuan cuti)
                    </label>
                  </div>

                  <div class="form-group mr-3 mb-2">
                    <label for="match_threshold" class="mr-2 mb-0 small">Threshold</label>
                    <input type="number" step="0.01" min="0.30" max="0.70"
                      class="form-control form-control-sm" id="match_threshold" name="match_threshold"
                      value="{{ number_format((float) $settings->match_threshold, 3) }}" style="width:100px;">
                    <small class="text-muted ml-2">0.40 (ketat) - 0.60 (longgar)</small>
                  </div>

                  @canRoute('face-recognition.settings')
                  <button type="submit" class="btn btn-primary btn-sm mb-2">
                    <i class="fas fa-save mr-1"></i> Simpan Pengaturan
                  </button>
                  @endcanRoute
                </form>
                <small class="text-muted d-block mt-2">
                  Jika OFF, tab Face Recognition pada halaman login dan slip payroll akan disembunyikan.
                </small>
              </div>
            </div>

            {{-- ================= TAB MENU ================= --}}
            <ul class="nav nav-tabs mb-3" role="tablist">
              <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#registerPane">
                  <i class="fas fa-user-plus mr-1"></i> Register Wajah
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#testPane">
                  <i class="fas fa-vial mr-1"></i> Test Face Recognition
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#dataPane">
                  <i class="fas fa-list mr-1"></i> Data Wajah ({{ $faces->count() }})
                </a>
              </li>
            </ul>

            <div class="tab-content">
              {{-- ================= REGISTER ================= --}}
              <div class="tab-pane fade show active" id="registerPane">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Register / Perbarui Wajah Karyawan</h6>
                  </div>
                  <div class="card-body">
                    <form id="registerFaceForm" action="{{ route('face-recognition.store') }}" method="POST">
                      @csrf
                      <input type="hidden" name="face_img" id="register_face_img">
                      <input type="hidden" name="descriptor" id="register_descriptor">

                      <div class="row">
                        <div class="col-lg-5">
                          <div class="form-group">
                            <label>NPK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="register_npk" name="npk"
                              list="npkList" placeholder="Contoh: C-00827" autocomplete="off" required>
                            <datalist id="npkList">
                              @foreach($users as $user)
                              <option value="{{ $user->npk }}">{{ $user->name }}</option>
                              @endforeach
                            </datalist>
                            <small class="text-muted">Pilih/ketik NPK, nama karyawan terisi otomatis (readonly).</small>
                          </div>

                          <div class="form-group">
                            <label>Nama Karyawan</label>
                            <input type="text" class="form-control" id="register_name" name="name"
                              placeholder="Terisi otomatis dari NPK" readonly>
                          </div>

                          <div id="registerExisting" class="alert alert-info py-2 px-3 small d-none"></div>

                          <div id="registerStatus" class="alert alert-secondary py-2 px-3 small">
                            Aktifkan kamera, lalu ambil wajah karyawan.
                          </div>

                          <button type="button" class="btn btn-outline-primary btn-block" id="registerCameraBtn">
                            <i class="fas fa-camera mr-1"></i> Aktifkan Kamera
                          </button>

                          <button type="button" class="btn btn-success btn-block" id="registerCaptureBtn" disabled>
                            <i class="fas fa-save mr-1"></i> Ambil &amp; Simpan Wajah
                          </button>

                          <div class="text-center mt-3">
                            <img id="registerPreview" class="img-thumbnail d-none" style="max-height:180px;" alt="Preview wajah">
                          </div>
                        </div>

                        <div class="col-lg-7">
                          <div class="camera-box-md">
                            <video id="registerVideo" class="face-video" autoplay muted playsinline></video>
                          </div>
                          <small class="text-muted d-block mt-2">
                            Pastikan wajah terlihat jelas, tidak tertutup masker, dan pencahayaan cukup.
                            Satu NPK hanya menyimpan satu data wajah (register ulang akan memperbarui data lama).
                          </small>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              {{-- ================= TEST ================= --}}
              <div class="tab-pane fade" id="testPane">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Test Face Recognition</h6>
                  </div>
                  <div class="card-body">
                    <div class="row">
                      <div class="col-lg-6">
                        <div class="camera-box-md">
                          <video id="testVideo" class="face-video" autoplay muted playsinline></video>
                        </div>
                        <div id="testStatus" class="alert alert-secondary py-2 px-3 small mt-3 mb-0">
                          Klik "Mulai Test" untuk memulai.
                        </div>
                        <div class="mt-3">
                          <button type="button" class="btn btn-success" id="testStartBtn">
                            <i class="fas fa-play mr-1"></i> Mulai Test
                          </button>
                          <button type="button" class="btn btn-outline-secondary" id="testStopBtn" disabled>
                            <i class="fas fa-stop mr-1"></i> Stop
                          </button>
                        </div>
                      </div>

                      <div class="col-lg-6">
                        <h6 class="font-weight-bold text-gray-800">Hasil Pencocokan</h6>
                        <table class="table table-sm table-bordered">
                          <tbody id="testResultBody">
                            <tr>
                              <th width="40%">Status</th>
                              <td><span class="badge badge-secondary">Belum ada hasil</span></td>
                            </tr>
                          </tbody>
                        </table>
                        <div class="small text-muted">
                          Test ini hanya membaca data wajah. Tidak melakukan login.
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {{-- ================= DATA ================= --}}
              <div class="tab-pane fade" id="dataPane">
                <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Data Wajah Terdaftar</h6>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-bordered table-sm" id="faceDataTable" width="100%">
                        <thead>
                          <tr>
                            <th width="45">#</th>
                            <th width="80">Foto</th>
                            <th>NPK</th>
                            <th>Nama</th>
                            <th>Status</th>
                            <th width="90">Hak Login</th>
                            <th>Terakhir Verifikasi</th>
                            <th>Register Oleh</th>
                            <th width="130">Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse($faces as $face)
                          <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                              <img src="{{ $face->face_img }}" class="face-thumb" alt="Wajah {{ $face->npk }}" loading="lazy">
                            </td>
                            <td>
                              <strong>{{ $face->npk }}</strong>
                              @if($face->user)
                              <br><span class="badge badge-light border">User aktif</span>
                              @else
                              <br><span class="badge badge-light border">Tanpa akun user</span>
                              @endif
                            </td>
                            <td>{{ $face->name ?: '-' }}</td>
                            <td>
                              <span class="badge badge-{{ $face->is_active ? 'success' : 'secondary' }}">
                                {{ $face->is_active ? 'Aktif' : 'Nonaktif' }}
                              </span>
                            </td>
                            <td>
                              @php
                                  $canLogin = (bool) ($face->can_login ?? false);
                              @endphp
                              <span class="badge badge-{{ $canLogin ? 'primary' : 'warning' }}">
                                {{ $canLogin ? 'Bisa Login' : 'Non-Login' }}
                              </span>
                            </td>
                            <td>{{ $face->last_verified_at ? $face->last_verified_at->diffForHumans() : 'Belum pernah' }}</td>
                            <td>{{ $face->registeredBy->name ?? '-' }}</td>
                            <td class="text-center">
                              @canRoute('face-recognition.can-login')
                              <button type="button" class="btn btn-{{ $canLogin ? 'primary' : 'secondary' }} btn-circle btn-sm btn-toggle-canlogin"
                                data-link="{{ route('face-recognition.can-login', $face) }}"
                                data-npk="{{ $face->npk }}"
                                title="{{ $canLogin ? 'Cabut hak login sistem' : 'Izinkan login sistem' }}">
                                <i class="fas {{ $canLogin ? 'fa-user-lock' : 'fa-user-shield' }}"></i>
                              </button>
                              @endcanRoute
                              @canRoute('face-recognition.toggle')
                              <button type="button" class="btn btn-warning btn-circle btn-sm btn-toggle-face"
                                data-link="{{ route('face-recognition.toggle', $face) }}"
                                data-npk="{{ $face->npk }}"
                                title="{{ $face->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="fas {{ $face->is_active ? 'fa-ban' : 'fa-check-circle' }}"></i>
                              </button>
                              @endcanRoute
                              @canRoute('face-recognition.destroy')
                              <button type="button" class="btn btn-danger btn-circle btn-sm btn-delete-face"
                                data-link="{{ route('face-recognition.destroy', $face) }}"
                                data-npk="{{ $face->npk }}"
                                title="Hapus data wajah">
                                <i class="fas fa-trash"></i>
                              </button>
                              @endcanRoute
                            </td>
                          </tr>
                          @empty
                          <tr>
                            <td colspan="9" class="text-center text-muted">Belum ada data wajah terdaftar.</td>
                          </tr>
                          @endforelse
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
    @include('layout.footer')
  </body>

  <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
  <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{ asset('vendor-face-api/face-api.js') }}"></script>
  <script src="{{ asset('js/face-recognition.js') }}"></script>

  <script>
    (function () {
      const modelUrl    = "{{ asset('vendor-face-api/model') }}";
      const lookupUrl   = "{{ route('face-recognition.lookup') }}";
      const identifyUrl = "{{ route('face-recognition.identify') }}";

      /* ============================ REGISTER ============================ */
      const regVideo     = document.getElementById('registerVideo');
      const regCameraBtn = document.getElementById('registerCameraBtn');
      const regCapture   = document.getElementById('registerCaptureBtn');
      const regStatus    = document.getElementById('registerStatus');
      const regNpk       = document.getElementById('register_npk');
      const regName      = document.getElementById('register_name');
      const regPreview   = document.getElementById('registerPreview');
      const regExisting  = document.getElementById('registerExisting');
      const regForm      = document.getElementById('registerFaceForm');
      const regImage     = document.getElementById('register_face_img');
      const regDescriptor = document.getElementById('register_descriptor');

      let regCameraActive = false;

      function setRegStatus(message, type) {
        regStatus.textContent = message;
        regStatus.className = 'alert py-2 px-3 small ' + (type === 'error'
          ? 'alert-danger'
          : (type === 'success' ? 'alert-success' : 'alert-secondary'));
      }

      function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
      }

      async function toggleRegisterCamera() {
        if (regCameraActive) {
          FaceRecognition.stopCamera(regVideo);
          regCameraActive = false;
          regCameraBtn.innerHTML = '<i class="fas fa-camera mr-1"></i> Aktifkan Kamera';
          regCapture.disabled = true;
          setRegStatus('Kamera dimatikan.', 'secondary');
          return;
        }

        regCameraBtn.disabled = true;
        setRegStatus('Memuat model dan menyalakan kamera...');

        try {
          await FaceRecognition.loadModels(modelUrl);
          await FaceRecognition.startCamera(regVideo);
          regCameraActive = true;
          regCameraBtn.innerHTML = '<i class="fas fa-power-off mr-1"></i> Matikan Kamera';
          regCapture.disabled = false;
          setRegStatus('Kamera aktif. Posisikan wajah lalu klik "Ambil & Simpan Wajah".');
        } catch (error) {
          console.error(error);
          setRegStatus('Gagal menyalakan kamera: ' + error.message, 'error');
        }

        regCameraBtn.disabled = false;
      }

      async function captureWajah() {
        const npk = (regNpk.value || '').trim().toUpperCase();

        if (!npk) {
          Swal.fire('NPK wajib diisi', 'Masukkan NPK karyawan terlebih dahulu.', 'warning');
          regNpk.focus();
          return;
        }

        if (!/^[A-Za-z0-9\-]+$/.test(npk)) {
          Swal.fire('NPK tidak valid', 'NPK hanya boleh berisi huruf, angka, dan tanda minus.', 'error');
          return;
        }

        if (!regCameraActive) {
          Swal.fire('Kamera belum aktif', 'Klik "Aktifkan Kamera" terlebih dahulu.', 'warning');
          return;
        }

        regCapture.disabled = true;
        setRegStatus('Mendeteksi wajah...');

        try {
          let detected = null;

          for (let i = 0; i < 5 && !detected; i++) {
            detected = await FaceRecognition.detect(regVideo);

            if (!detected) {
              await sleep(500);
            }
          }

          if (!detected) {
            Swal.fire('Wajah tidak terdeteksi', 'Pastikan wajah terlihat jelas di kamera lalu coba lagi.', 'error');
            setRegStatus('Wajah tidak terdeteksi. Coba lagi.', 'error');
            regCapture.disabled = false;
            return;
          }

          const image = FaceRecognition.snapshot(regVideo, 480);

          if (!image) {
            Swal.fire('Gagal mengambil gambar', 'Frame kamera belum siap. Coba lagi.', 'error');
            regCapture.disabled = false;
            return;
          }

          regImage.value = image;
          regDescriptor.value = JSON.stringify(detected.descriptor);
          regPreview.src = image;
          regPreview.classList.remove('d-none');

          FaceRecognition.stopCamera(regVideo);
          regCameraActive = false;
          regCameraBtn.innerHTML = '<i class="fas fa-camera mr-1"></i> Aktifkan Kamera';

          setRegStatus('Wajah berhasil diambil. Menyimpan data...', 'success');

          regForm.submit();
        } catch (error) {
          console.error(error);
          Swal.fire('Terjadi kesalahan', error.message, 'error');
          setRegStatus('Gagal mengambil wajah: ' + error.message, 'error');
          regCapture.disabled = false;
        }
      }

      async function lookupNpk() {
        const npk = (regNpk.value || '').trim().toUpperCase();

        if (!npk) {
          regName.value = '';
          regExisting.classList.add('d-none');
          regExisting.innerHTML = '';
          return;
        }

        try {
          const response = await fetch(lookupUrl + '?npk=' + encodeURIComponent(npk), {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();

          // Nama karyawan selalu terisi otomatis dari NPK (field readonly).
          regName.value = data.name ? data.name : '';

          if (data.face) {
            regExisting.classList.remove('d-none');
            regExisting.innerHTML = '<i class="fas fa-info-circle mr-1"></i> NPK ini sudah memiliki data wajah. '
              + 'Menyimpan ulang akan memperbarui data lama.';
          } else {
            regExisting.classList.add('d-none');
            regExisting.innerHTML = '';

            if (!data.has_user) {
              regExisting.classList.remove('d-none');
              regExisting.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> NPK ini belum terdaftar di tabel users. '
                + 'Wajah tetap bisa dipakai untuk slip payroll, tetapi belum bisa dipakai login HRIS.';
            }
          }
        } catch (error) {
          console.warn(error);
        }
      }

      regCameraBtn.addEventListener('click', toggleRegisterCamera);
      regCapture.addEventListener('click', captureWajah);
      regNpk.addEventListener('change', lookupNpk);
      regNpk.addEventListener('blur', lookupNpk);

      /* ============================ TEST ============================ */
      const testVideo   = document.getElementById('testVideo');
      const testStart   = document.getElementById('testStartBtn');
      const testStop    = document.getElementById('testStopBtn');
      const testStatus  = document.getElementById('testStatus');
      const testResult  = document.getElementById('testResultBody');

      let testActive = false;
      let testBusy = false;
      let testTimer = null;

      function setTestStatus(message, type) {
        testStatus.textContent = message;
        testStatus.className = 'alert py-2 px-3 small mt-3 mb-0 ' + (type === 'error'
          ? 'alert-danger'
          : (type === 'success' ? 'alert-success' : 'alert-secondary'));
      }

      function renderTestResult(data) {
        if (!data) {
          testResult.innerHTML = '<tr><th width="40%">Status</th><td><span class="badge badge-secondary">Belum ada hasil</span></td></tr>';
          return;
        }

        if (data.message && data.npk === undefined) {
          testResult.innerHTML = '<tr><th width="40%">Status</th><td><span class="badge badge-danger">Gagal</span><br>'
            + escapeHtml(data.message) + '</td></tr>';
          return;
        }

        const badge = data.matched
          ? '<span class="badge badge-success">COCOK</span>'
          : '<span class="badge badge-danger">TIDAK COCOK</span>';

        let userInfo = '<span class="text-muted">Tidak terdaftar di tabel users</span>';

        if (data.user) {
          userInfo = escapeHtml(data.user.name) + ' (' + escapeHtml(data.user.npk) + ') '
            + (data.user.disabled
              ? '<span class="badge badge-danger">disabled</span>'
              : '<span class="badge badge-success">aktif</span>');
        }

        testResult.innerHTML =
          '<tr><th width="40%">Status</th><td>' + badge + '</td></tr>' +
          '<tr><th>NPK</th><td>' + escapeHtml(data.npk || '-') + '</td></tr>' +
          '<tr><th>Nama</th><td>' + escapeHtml(data.name || '-') + '</td></tr>' +
          '<tr><th>Distance</th><td>' + (data.distance !== undefined && data.distance !== null ? data.distance : '-') + '</td></tr>' +
          '<tr><th>Threshold</th><td>' + (data.threshold !== undefined ? data.threshold : '-') + '</td></tr>' +
          '<tr><th>Margin</th><td>' + (data.margin !== null && data.margin !== undefined ? data.margin : '-') + '</td></tr>' +
          '<tr><th>User HRIS</th><td>' + userInfo + '</td></tr>' +
          '<tr><th>Bisa Login</th><td>' + (data.can_login
            ? '<span class="badge badge-success">Ya</span>'
            : '<span class="badge badge-secondary">Tidak</span>') + '</td></tr>';
      }

      function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      async function testLoop() {
        if (!testActive) {
          return;
        }

        if (!testBusy && testVideo.readyState >= 2) {
          testBusy = true;

          try {
            const detected = await FaceRecognition.detect(testVideo);

            if (detected) {
              setTestStatus('Wajah terdeteksi, mencocokkan...');
              const data = await FaceRecognition.postJson(identifyUrl, {
                descriptor: detected.descriptor
              });

              renderTestResult(data);

              if (data.matched) {
                setTestStatus('Wajah dikenali sebagai ' + data.npk + ' (' + data.distance + ').', 'success');
                testActive = false;
                FaceRecognition.stopCamera(testVideo);
                testStart.disabled = false;
                testStop.disabled = true;
                testBusy = false;
                return;
              }

              setTestStatus(data.message || 'Wajah belum cocok.', 'error');
            } else {
              setTestStatus('Wajah belum terdeteksi. Hadapkan wajah ke kamera.', 'error');
            }
          } catch (error) {
            console.error(error);
            setTestStatus('Gagal memproses wajah: ' + error.message, 'error');
          }

          testBusy = false;
        }

        if (testActive) {
          testTimer = setTimeout(testLoop, 800);
        }
      }

      async function startTest() {
        if (testActive) {
          return;
        }

        testStart.disabled = true;
        testStop.disabled = true;
        setTestStatus('Memuat model dan menyalakan kamera...');

        try {
          await FaceRecognition.loadModels(modelUrl);
          await FaceRecognition.startCamera(testVideo);
        } catch (error) {
          console.error(error);
          setTestStatus('Gagal menyalakan kamera: ' + error.message, 'error');
          testStart.disabled = false;
          return;
        }

        testActive = true;
        testBusy = false;
        testStop.disabled = false;
        setTestStatus('Posisikan wajah di dalam kamera...');
        testLoop();
      }

      function stopTest() {
        testActive = false;
        testBusy = false;

        if (testTimer) {
          clearTimeout(testTimer);
          testTimer = null;
        }

        FaceRecognition.stopCamera(testVideo);
        testStart.disabled = false;
        testStop.disabled = true;
        setTestStatus('Test dihentikan.', 'secondary');
      }

      testStart.addEventListener('click', startTest);
      testStop.addEventListener('click', stopTest);

      /* ============================ TAB ============================ */
      jQuery('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = jQuery(e.target).attr('href');

        if (target !== '#registerPane' && regCameraActive) {
          FaceRecognition.stopCamera(regVideo);
          regCameraActive = false;
          regCameraBtn.innerHTML = '<i class="fas fa-camera mr-1"></i> Aktifkan Kamera';
          regCapture.disabled = true;
        }

        if (target !== '#testPane' && testActive) {
          stopTest();
        }
      });

      window.addEventListener('beforeunload', function () {
        FaceRecognition.stopCamera(regVideo);
        FaceRecognition.stopCamera(testVideo);
      });

      /* ============================ DATA TABLE ============================ */
      jQuery(function () {
        if (jQuery('#faceDataTable').length && !jQuery.fn.DataTable.isDataTable('#faceDataTable')) {
          jQuery('#faceDataTable').DataTable({
            order: [[0, 'asc']],
            pageLength: 25,
            language: {
              emptyTable: 'Belum ada data wajah terdaftar.'
            }
          });
        }
      });

      jQuery('.btn-toggle-face').on('click', function () {
        const link = jQuery(this).data('link');
        const npk  = jQuery(this).data('npk');

        Swal.fire({
          icon: 'question',
          title: 'Ubah status wajah?',
          text: 'Data wajah NPK ' + npk + ' akan diubah statusnya.',
          showCancelButton: true,
          confirmButtonText: 'Ya, ubah',
          cancelButtonText: 'Batal'
        }).then(function (result) {
          if (result.isConfirmed) {
            submitDynamicForm(link, 'PATCH');
          }
        });
      });

      jQuery('.btn-toggle-canlogin').on('click', function () {
        const link = jQuery(this).data('link');
        const npk  = jQuery(this).data('npk');

        Swal.fire({
          icon: 'question',
          title: 'Ubah hak login wajah?',
          text: 'Izin login sistem untuk NPK ' + npk + ' akan diubah. Wajah tetap bisa dipakai untuk fitur non-login (lihat slip, pengajuan cuti, dll).',
          showCancelButton: true,
          confirmButtonText: 'Ya, ubah',
          cancelButtonText: 'Batal'
        }).then(function (result) {
          if (result.isConfirmed) {
            submitDynamicForm(link, 'PATCH');
          }
        });
      });

      jQuery('.btn-delete-face').on('click', function () {
        const link = jQuery(this).data('link');
        const npk  = jQuery(this).data('npk');

        Swal.fire({
          icon: 'warning',
          title: 'Hapus data wajah?',
          text: 'Data wajah NPK ' + npk + ' akan dihapus permanen.',
          showCancelButton: true,
          confirmButtonText: 'Ya, hapus',
          cancelButtonText: 'Batal'
        }).then(function (result) {
          if (result.isConfirmed) {
            submitDynamicForm(link, 'DELETE');
          }
        });
      });

      function submitDynamicForm(action, method) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = '{{ csrf_token() }}';
        form.appendChild(token);

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = method;
        form.appendChild(methodInput);

        document.body.appendChild(form);
        form.submit();
      }
    })();
  </script>
</html>
