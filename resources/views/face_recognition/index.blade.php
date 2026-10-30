<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <style>
      .face-video {
          width: 100%;
          height: 100%;
          object-fit: cover;
          transform: scaleX(-1);
          display: block;
      }
      .face-thumb {
          width: 56px;
          height: 56px;
          object-fit: cover;
          border-radius: 8px;
          border: 1px solid #e3e6f0;
      }
      .camera-square {
          width: 100%;
          max-width: 380px;
          aspect-ratio: 1 / 1;
          margin: 0 auto;
          position: relative;
          border-radius: 16px;
          overflow: hidden;
          background: #000;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
      }
      .camera-square video {
          width: 100%;
          height: 100%;
          object-fit: cover;
          transform: scaleX(-1);
          display: block;
      }
      .camera-square canvas {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          object-fit: cover;
          transform: scaleX(-1);
          display: none;
      }
      .scanner-guide-oval {
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          width: 65%;
          height: 80%;
          border: 3px dashed rgba(255, 255, 255, 0.7);
          border-radius: 50%;
          box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.25);
          pointer-events: none;
          transition: all 0.3s ease;
          z-index: 2;
      }
      .scanner-guide-oval.success {
          border-color: #28a745;
          border-style: solid;
          box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.35), 0 0 15px rgba(40, 167, 69, 0.6);
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
              <span class="badge badge-{{ $settings->is_enabled ? 'success' : 'secondary' }} px-3 py-2">
                Face Recognition: {{ $settings->is_enabled ? 'ON' : 'OFF' }}
              </span>
            </div>

            {{-- ================= PENGATURAN GLOBAL ================= --}}
            <div class="card shadow mb-4 {{ $settings->is_enabled ? 'border-left-success' : 'border-left-secondary' }}">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                  <i class="fas fa-cogs mr-1"></i> Pengaturan Face Recognition &amp; QR Code Login
                </h6>
              </div>
              <div class="card-body">
                <form action="{{ route('face-recognition.settings') }}" method="POST">
                  @csrf
                  @method('PATCH')

                  <div class="row align-items-center mb-3">
                    <div class="col-md-6 mb-2">
                      <input type="hidden" name="is_enabled" value="0">
                      <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="is_enabled" name="is_enabled"
                          value="1" {{ ($settings->is_enabled ?? true) ? 'checked' : '' }}>
                        <label class="custom-control-label font-weight-bold text-gray-800" for="is_enabled">
                          <i class="fas fa-camera mr-1 text-primary"></i> Aktifkan Fitur Face Recognition (Global)
                        </label>
                      </div>
                      <small class="text-muted d-block ml-4">
                        Jika OFF, seluruh tab/fitur Face Recognition (login, slip payroll, cuti) dinonaktifkan.
                      </small>
                    </div>

                    <div class="col-md-6 mb-2">
                      <div class="form-inline">
                        <label for="match_threshold" class="mr-2 mb-0 font-weight-bold small text-gray-800">
                          <i class="fas fa-sliders-h mr-1 text-info"></i> Threshold Wajah:
                        </label>
                        <input type="number" step="0.01" min="0.30" max="0.70"
                          class="form-control form-control-sm" id="match_threshold" name="match_threshold"
                          value="{{ number_format((float) ($settings->match_threshold ?? 0.500), 3) }}" style="width:90px;">
                        <small class="text-muted ml-2">0.40 (ketat) - 0.60 (longgar)</small>
                      </div>
                    </div>
                  </div>

                  <hr class="my-3">

                  <div class="mb-2">
                    <h6 class="font-weight-bold text-gray-900 mb-2">
                      <i class="fas fa-qrcode mr-1 text-success"></i> Pengaturan QR Code Login (Per Menu)
                    </h6>
                    <small class="text-muted d-block mb-3">
                      Atur menu mana saja yang diizinkan menggunakan kamera scan QR Code ID Card. Jika status OFF, kamera dan tab QR Code pada menu tersebut tidak akan aktif.
                    </small>
                  </div>

                  <div class="row">
                    {{-- Toggle QR Login Utama --}}
                    <div class="col-md-6 col-lg-3 mb-3">
                      <div class="border rounded p-2 bg-light">
                        <input type="hidden" name="is_qr_login_enabled" value="0">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" id="is_qr_login_enabled" name="is_qr_login_enabled"
                            value="1" {{ ($settings->is_qr_login_enabled ?? true) ? 'checked' : '' }}>
                          <label class="custom-control-label font-weight-bold small" for="is_qr_login_enabled">
                            Login Utama (/login)
                          </label>
                        </div>
                        <small class="text-muted d-block mt-1">Scan ID Card Sign In</small>
                      </div>
                    </div>

                    {{-- Toggle QR Slip Gaji --}}
                    <div class="col-md-6 col-lg-3 mb-3">
                      <div class="border rounded p-2 bg-light">
                        <input type="hidden" name="is_qr_payroll_enabled" value="0">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" id="is_qr_payroll_enabled" name="is_qr_payroll_enabled"
                            value="1" {{ ($settings->is_qr_payroll_enabled ?? true) ? 'checked' : '' }}>
                          <label class="custom-control-label font-weight-bold small" for="is_qr_payroll_enabled">
                            Slip Gaji (/employee-payroll)
                          </label>
                        </div>
                        <small class="text-muted d-block mt-1">Scan QR ID Card Payroll</small>
                      </div>
                    </div>

                    {{-- Toggle QR THR --}}
                    <div class="col-md-6 col-lg-3 mb-3">
                      <div class="border rounded p-2 bg-light">
                        <input type="hidden" name="is_qr_thr_enabled" value="0">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" id="is_qr_thr_enabled" name="is_qr_thr_enabled"
                            value="1" {{ ($settings->is_qr_thr_enabled ?? true) ? 'checked' : '' }}>
                          <label class="custom-control-label font-weight-bold small" for="is_qr_thr_enabled">
                            THR (/employee-thr)
                          </label>
                        </div>
                        <small class="text-muted d-block mt-1">Scan QR ID Card THR</small>
                      </div>
                    </div>

                    {{-- Toggle QR Cuti --}}
                    <div class="col-md-6 col-lg-3 mb-3">
                      <div class="border rounded p-2 bg-light">
                        <input type="hidden" name="is_qr_cuti_enabled" value="0">
                        <div class="custom-control custom-switch">
                          <input type="checkbox" class="custom-control-input" id="is_qr_cuti_enabled" name="is_qr_cuti_enabled"
                            value="1" {{ ($settings->is_qr_cuti_enabled ?? true) ? 'checked' : '' }}>
                          <label class="custom-control-label font-weight-bold small" for="is_qr_cuti_enabled">
                            Pengajuan Cuti (/pengajuan-cuti)
                          </label>
                        </div>
                        <small class="text-muted d-block mt-1">Scan QR ID Card Cuti</small>
                      </div>
                    </div>
                  </div>

                  @canRoute('face-recognition.settings')
                  <div class="mt-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm">
                      <i class="fas fa-save mr-1"></i> Simpan Pengaturan
                    </button>
                  </div>
                  @endcanRoute
                </form>
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
                            <label class="font-weight-bold text-gray-800">NPK <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="register_npk" name="npk"
                              list="npkList" placeholder="Contoh: C-00827" autocomplete="off" required>
                            <datalist id="npkList">
                              @foreach($users as $user)
                              <option value="{{ $user->npk }}">{{ $user->name }}</option>
                              @endforeach
                            </datalist>
                            <small class="text-muted">Ketik NPK, nama akan terisi otomatis dan readonly.</small>
                          </div>

                          <div class="form-group">
                            <label class="font-weight-bold text-gray-800">Nama Karyawan</label>
                            <input type="text" class="form-control bg-light font-weight-bold" id="register_name" name="name"
                              placeholder="Nama karyawan terisi otomatis" readonly>
                          </div>

                          <div id="registerExisting" class="alert alert-info py-2 px-3 small d-none"></div>

                          <div id="registerStatus" class="alert alert-secondary py-2 px-3 small font-weight-bold text-center">
                            Aktifkan kamera, lalu posisikan wajah di dalam oval.
                          </div>

                          <button type="button" class="btn btn-outline-primary btn-block shadow-sm" id="registerCameraBtn">
                            <i class="fas fa-camera mr-1"></i> Aktifkan Kamera
                          </button>

                          <button type="button" class="btn btn-success btn-block shadow-sm font-weight-bold" id="registerCaptureBtn" disabled>
                            <i class="fas fa-save mr-1"></i> Ambil &amp; Simpan Wajah
                          </button>

                          <div class="text-center mt-3">
                            <img id="registerPreview" class="img-thumbnail d-none" style="max-height:180px;" alt="Preview wajah">
                          </div>
                        </div>

                        <div class="col-lg-7 text-center">
                          <div class="camera-square shadow-sm mb-2">
                            <video id="registerVideo" class="face-video" autoplay muted playsinline></video>
                            <canvas id="registerCanvas" style="display:none;"></canvas>
                            <div class="scanner-guide-oval" id="registerGuide"></div>
                          </div>
                          <small class="text-muted d-block mt-2">
                            Posisikan wajah di tengah panduan oval. Kamera mendeteksi wajah secara otomatis (oval akan berubah hijau saat stabil).
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
                      <div class="col-lg-6 text-center">
                        <div class="camera-square shadow-sm mb-2">
                          <video id="testVideo" class="face-video" autoplay muted playsinline></video>
                          <div class="scanner-guide-oval" id="testGuide"></div>
                        </div>
                        <div id="testStatus" class="alert alert-secondary py-2 px-3 small mt-3 mb-0 font-weight-bold text-center">
                          Klik "Mulai Test" untuk menyalakan kamera.
                        </div>
                        <div class="mt-3">
                          <button type="button" class="btn btn-success px-4 font-weight-bold shadow-sm" id="testStartBtn">
                            <i class="fas fa-play mr-1"></i> Mulai Test
                          </button>
                          <button type="button" class="btn btn-outline-secondary px-4 shadow-sm" id="testStopBtn" disabled>
                            <i class="fas fa-stop mr-1"></i> Stop
                          </button>
                        </div>
                      </div>

                      <div class="col-lg-6">
                        <h6 class="font-weight-bold text-gray-800">Hasil Pencocokan Biometrik</h6>
                        <table class="table table-sm table-bordered">
                          <tbody id="testResultBody">
                            <tr>
                              <th width="40%">Status</th>
                              <td><span class="badge badge-secondary">Belum ada hasil</span></td>
                            </tr>
                          </tbody>
                        </table>
                        <div class="small text-muted">
                          Test ini otomatis mendeteksi wajah dan mencocokkan data biometrik secara langsung tanpa melakukan login.
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
                            <th width="40">#</th>
                            <th width="70">Foto</th>
                            <th>NPK</th>
                            <th>Nama</th>
                            <th>Status Wajah</th>
                            <th class="text-center">Izin Login</th>
                            <th>Terakhir Verifikasi</th>
                            <th>Register Oleh</th>
                            <th width="140" class="text-center">Aksi</th>
                          </tr>
                        </thead>
                        <tbody>
                          @forelse($faces as $face)
                          @php
                              $cleanNpk = strtoupper(trim($face->npk));
                              $userObj  = $usersByNpk->get($cleanNpk) ?? null;
                          @endphp
                          <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="text-center">
                              <img src="{{ $face->face_img }}" class="face-thumb rounded border shadow-sm btn-preview-photo"
                                alt="Wajah {{ $face->npk }}" loading="lazy" style="cursor: pointer; width: 50px; height: 50px; object-fit: cover;"
                                data-img="{{ $face->face_img }}"
                                data-npk="{{ $face->npk }}"
                                data-name="{{ $face->name }}"
                                title="Klik untuk perbesar foto">
                            </td>
                            <td>
                              <strong>{{ $face->npk }}</strong>
                              @if($userObj)
                                @if((int)($userObj->disabled ?? 0) === 0)
                                <br><span class="badge badge-success"><i class="fas fa-user-check mr-1"></i> User aktif</span>
                                @else
                                <br><span class="badge badge-warning text-dark"><i class="fas fa-user-slash mr-1"></i> Akun dinonaktifkan</span>
                                @endif
                              @else
                                <br><span class="badge badge-light border text-muted"><i class="fas fa-user-times mr-1"></i> Tanpa akun user</span>
                              @endif
                            </td>
                            <td>{{ $face->name ?: '-' }}</td>
                            <td>
                              <span class="badge badge-{{ $face->is_active ? 'success' : 'secondary' }}">
                                {{ $face->is_active ? 'Aktif' : 'Nonaktif' }}
                              </span>
                            </td>
                            <td class="text-center">
                              @if($face->can_login)
                                <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Bisa Login</span>
                              @else
                                <span class="badge badge-secondary"><i class="fas fa-times-circle mr-1"></i>Tidak Bisa</span>
                              @endif
                            </td>
                            <td>{{ $face->last_verified_at ? $face->last_verified_at->diffForHumans() : 'Belum pernah' }}</td>
                            <td>{{ $face->registeredBy->name ?? '-' }}</td>
                            <td class="text-center">
                              {{-- Tombol Toggle Izin Login --}}
                              <button type="button" class="btn btn-{{ $face->can_login ? 'success' : 'secondary' }} btn-circle btn-sm btn-toggle-can-login"
                                data-link="{{ route('face-recognition.can-login', $face) }}"
                                data-npk="{{ $face->npk }}"
                                data-can-login="{{ $face->can_login ? '1' : '0' }}"
                                title="{{ $face->can_login ? 'Izin Login: BISA (Klik untuk Larang Login)' : 'Izin Login: TIDAK (Klik untuk Izinkan Login)' }}">
                                <i class="fas {{ $face->can_login ? 'fa-user-check' : 'fa-user-lock' }}"></i>
                              </button>

                              @canRoute('face-recognition.toggle')
                              <button type="button" class="btn btn-warning btn-circle btn-sm btn-toggle-face"
                                data-link="{{ route('face-recognition.toggle', $face) }}"
                                data-npk="{{ $face->npk }}"
                                title="{{ $face->is_active ? 'Nonaktifkan status wajah' : 'Aktifkan status wajah' }}">
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

            {{-- Modal Preview Foto Wajah --}}
            <div class="modal fade" id="facePhotoModal" tabindex="-1" role="dialog" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content shadow-lg border-0">
                  <div class="modal-header bg-primary text-white py-2">
                    <h6 class="modal-title font-weight-bold" id="facePhotoModalTitle">
                      <i class="fas fa-id-badge mr-1"></i> Foto Wajah Karyawan
                    </h6>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body text-center p-4">
                    <img id="facePhotoModalImg" src="" alt="Foto Wajah" class="img-fluid rounded shadow border" style="max-height: 400px; width: auto;">
                    <div class="mt-3">
                      <h5 class="font-weight-bold text-gray-900 mb-1" id="facePhotoModalName">-</h5>
                      <span class="badge badge-primary px-3 py-1 font-weight-bold" id="facePhotoModalNpk">-</span>
                    </div>
                  </div>
                  <div class="modal-footer py-2 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-dismiss="modal">
                      <i class="fas fa-times mr-1"></i> Tutup
                    </button>
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
      const regGuide     = document.getElementById('registerGuide');
      const regCanvas    = document.getElementById('registerCanvas');
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
      let regDetectTimer  = null;

      function setRegStatus(message, type) {
        regStatus.textContent = message;
        regStatus.className = 'alert py-2 px-3 small font-weight-bold text-center ' + (type === 'error'
          ? 'alert-danger'
          : (type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-secondary')));
      }

      function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
      }

      function startRegDetectionLoop() {
        if (regDetectTimer) clearInterval(regDetectTimer);
        regDetectTimer = setInterval(async () => {
          if (!regCameraActive || !regVideo || regVideo.readyState < 2) return;
          try {
            const detected = await FaceRecognition.detect(regVideo);
            if (detected && detected.descriptor) {
              if (regGuide) regGuide.classList.add('success');
              setRegStatus('Wajah terdeteksi di dalam oval! Klik "Ambil & Simpan Wajah".', 'success');
              regCapture.disabled = false;
            } else {
              if (regGuide) regGuide.classList.remove('success');
              setRegStatus('Posisikan wajah di tengah panduan oval...', 'secondary');
              regCapture.disabled = true;
            }
          } catch (e) {}
        }, 300);
      }

      function stopRegDetectionLoop() {
        if (regDetectTimer) {
          clearInterval(regDetectTimer);
          regDetectTimer = null;
        }
        if (regGuide) regGuide.classList.remove('success');
      }

      async function toggleRegisterCamera() {
        if (regCameraActive) {
          stopRegDetectionLoop();
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
          setRegStatus('Kamera aktif. Posisikan wajah di tengah oval.');
          startRegDetectionLoop();
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
        setRegStatus('Menganalisis wajah...', 'info');

        try {
          let detected = null;

          for (let i = 0; i < 5 && !detected; i++) {
            detected = await FaceRecognition.detect(regVideo);
            if (!detected) {
              await sleep(400);
            }
          }

          if (!detected || !detected.descriptor) {
            Swal.fire('Wajah tidak terdeteksi', 'Pastikan wajah terlihat jelas di dalam panduan oval lalu coba lagi.', 'error');
            setRegStatus('Wajah tidak terdeteksi di dalam oval. Coba lagi.', 'error');
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
          regDescriptor.value = JSON.stringify(Array.from(detected.descriptor));
          regPreview.src = image;
          regPreview.classList.remove('d-none');

          setRegStatus('Wajah berhasil diambil! Menyimpan ke database...', 'success');
          stopRegDetectionLoop();
          FaceRecognition.stopCamera(regVideo);
          regCameraActive = false;
          regCameraBtn.innerHTML = '<i class="fas fa-camera mr-1"></i> Aktifkan Kamera';

          Swal.fire({
            title: 'Menyimpan...',
            text: 'Sedang menyimpan data biometrik wajah.',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
          });

          regForm.submit();
        } catch (error) {
          console.error(error);
          setRegStatus('Gagal mengambil wajah: ' + error.message, 'error');
          regCapture.disabled = false;
        }
      }

      async function lookupNpk() {
        const npk = (regNpk.value || '').trim().toUpperCase();

        if (!npk) {
          regName.value = '';
          regExisting.classList.add('d-none');
          return;
        }

        // Cek langsung datalist option
        const opt = document.querySelector('#npkList option[value="' + npk + '"]');
        if (opt && opt.textContent) {
          regName.value = opt.textContent;
        }

        try {
          const response = await fetch(lookupUrl + '?npk=' + encodeURIComponent(npk), {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();

          if (data.name) {
            regName.value = data.name;
          }

          if (data.face) {
            regExisting.classList.remove('d-none');
            regExisting.className = 'alert alert-info py-2 px-3 small';
            regExisting.innerHTML = '<i class="fas fa-info-circle mr-1"></i> NPK ini sudah memiliki data wajah. '
              + 'Menyimpan ulang akan memperbarui data lama.';
          } else {
            regExisting.classList.add('d-none');
            regExisting.innerHTML = '';

            if (!data.has_user) {
              regExisting.classList.remove('d-none');
              regExisting.className = 'alert alert-warning py-2 px-3 small';
              regExisting.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> NPK ini belum terdaftar di tabel users. '
                + 'Wajah tetap bisa disimpan, namun belum dapat digunakan untuk login HRIS.';
            }
          }
        } catch (error) {
          console.warn(error);
        }
      }

      regCameraBtn.addEventListener('click', toggleRegisterCamera);
      regCapture.addEventListener('click', captureWajah);
      regNpk.addEventListener('input', lookupNpk);
      regNpk.addEventListener('change', lookupNpk);
      regNpk.addEventListener('blur', lookupNpk);

      /* ============================ TEST ============================ */
      const testVideo   = document.getElementById('testVideo');
      const testGuide   = document.getElementById('testGuide');
      const testStart   = document.getElementById('testStartBtn');
      const testStop    = document.getElementById('testStopBtn');
      const testStatus  = document.getElementById('testStatus');
      const testResult  = document.getElementById('testResultBody');

      let testActive = false;
      let testBusy = false;
      let testTimer = null;

      function setTestStatus(message, type) {
        testStatus.textContent = message;
        testStatus.className = 'alert py-2 px-3 small mt-3 mb-0 font-weight-bold text-center ' + (type === 'error'
          ? 'alert-danger'
          : (type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-secondary')));
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
          ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>COCOK</span>'
          : '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>TIDAK COCOK</span>';

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

            if (detected && detected.descriptor) {
              if (testGuide) testGuide.classList.add('success');
              setTestStatus('Wajah terdeteksi! Mencocokkan data biometrik...', 'info');

              const data = await FaceRecognition.postJson(identifyUrl, {
                descriptor: detected.descriptor
              });

              renderTestResult(data);

              if (data.matched) {
                setTestStatus('Wajah dikenali sebagai ' + data.npk + ' (' + data.distance + ').', 'success');
              } else {
                setTestStatus(data.message || 'Wajah belum cocok dengan data tersimpan.', 'error');
              }
            } else {
              if (testGuide) testGuide.classList.remove('success');
              setTestStatus('Mendeteksi wajah... Hadapkan wajah ke panduan oval.', 'secondary');
            }
          } catch (error) {
            console.error(error);
            setTestStatus('Gagal memproses wajah: ' + error.message, 'error');
          }

          testBusy = false;
        }

        if (testActive) {
          testTimer = setTimeout(testLoop, 600);
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
        setTestStatus('Kamera aktif. Posisikan wajah di dalam oval...');
        testLoop();
      }

      function stopTest() {
        testActive = false;
        testBusy = false;

        if (testTimer) {
          clearTimeout(testTimer);
          testTimer = null;
        }

        if (testGuide) testGuide.classList.remove('success');
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
          stopRegDetectionLoop();
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
        stopRegDetectionLoop();
        FaceRecognition.stopCamera(regVideo);
        FaceRecognition.stopCamera(testVideo);
      });

      /* ============================ DATA TABLE & ACTIONS ============================ */
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

        // Modal Preview Foto Wajah
        jQuery(document).on('click', '.btn-preview-photo', function () {
          const imgUrl = jQuery(this).data('img');
          const npk    = jQuery(this).data('npk');
          const name   = jQuery(this).data('name') || '-';

          jQuery('#facePhotoModalImg').attr('src', imgUrl);
          jQuery('#facePhotoModalNpk').text('NPK: ' + npk);
          jQuery('#facePhotoModalName').text(name);
          jQuery('#facePhotoModal').modal('show');
        });

        // Toggle Izin Login Wajah
        jQuery(document).on('click', '.btn-toggle-can-login', function () {
          const link = jQuery(this).data('link');
          const npk  = jQuery(this).data('npk');
          const current = jQuery(this).data('can-login') === '1' || jQuery(this).data('can-login') === 1;
          const targetText = current ? 'TIDAK BISA LOGIN' : 'BISA LOGIN';

          Swal.fire({
            icon: 'question',
            title: 'Ubah Izin Login?',
            text: 'Ubah status izin login biometrik wajah untuk NPK ' + npk + ' menjadi: ' + targetText + '?',
            showCancelButton: true,
            confirmButtonText: 'Ya, Ubah',
            cancelButtonText: 'Batal',
            confirmButtonColor: current ? '#e74a3b' : '#1cc88a'
          }).then(function (result) {
            if (result.isConfirmed) {
              submitDynamicForm(link, 'PATCH');
            }
          });
        });

        // Toggle Status Wajah
        jQuery(document).on('click', '.btn-toggle-face', function () {
          const link = jQuery(this).data('link');
          const npk  = jQuery(this).data('npk');

          Swal.fire({
            icon: 'question',
            title: 'Ubah status wajah?',
            text: 'Data wajah NPK ' + npk + ' akan diubah status aktif/nonaktifnya.',
            showCancelButton: true,
            confirmButtonText: 'Ya, ubah',
            cancelButtonText: 'Batal'
          }).then(function (result) {
            if (result.isConfirmed) {
              submitDynamicForm(link, 'PATCH');
            }
          });
        });

        // Hapus Data Wajah
        jQuery(document).on('click', '.btn-delete-face', function () {
          const link = jQuery(this).data('link');
          const npk  = jQuery(this).data('npk');

          Swal.fire({
            icon: 'warning',
            title: 'Hapus data wajah?',
            text: 'Data wajah NPK ' + npk + ' akan dihapus permanen dari sistem.',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#e74a3b'
          }).then(function (result) {
            if (result.isConfirmed) {
              submitDynamicForm(link, 'DELETE');
            }
          });
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
