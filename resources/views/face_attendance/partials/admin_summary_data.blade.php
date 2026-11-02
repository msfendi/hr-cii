                <!-- Statistik -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-primary shadow h-100 stat-card border-primary">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-uppercase text-gray-600">Total Karyawan</div>
                                <div class="h2 font-weight-bold text-gray-800 mb-0">{{ count($groupedAll) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-info shadow h-100 stat-card border-info">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-uppercase text-gray-600">Total Scan</div>
                                <div class="h2 font-weight-bold text-gray-800 mb-0">{{ $totalScan }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-success shadow h-100 stat-card border-success">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-uppercase text-gray-600">Total Hari Kerja</div>
                                <div class="h2 font-weight-bold text-gray-800 mb-0">
                                    {{ collect($groupedAll)->sum('days') }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card border-left-warning shadow h-100 stat-card border-warning">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-uppercase text-gray-600">Bulan</div>
                                <div class="h5 font-weight-bold text-gray-800 mb-0">
                                    {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Diagnostik rekap kosong /-ringkasan sumber data -->
                @if(isset($diagnostics))
                    @if(!$diagnostics['has_data'])
                        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Tidak ada data presensi pada bulan {{ \Carbon\Carbon::parse($month . '-01')->translatedFormat('F Y') }}
                            </h6>
                            <p class="mb-1">
                                Sumber data: tabel <code>att_log</code> (SQL Server <code>cii</code>).
                                <strong>{{ number_format($diagnostics['total_scans']) }}</strong> baris scan ditemukan pada periode ini.
                            </p>
                            <p class="mb-0">
                                @if(!empty($availableMonths))
                                    Pilih salah satu <strong>bulan tersedia</strong> di atas (data sudah ada di <code>att_log</code>).
                                @else
                                    Silakan pilih bulan lain pada filter di atas.
                                @endif
                                Data fingerprint di-sync dari mesin FingerSpot melalui menu
                                <strong>Absensi Finger</strong> &rarr; <strong>Sync</strong>.
                            </p>
                        </div>
                    @elseif($diagnostics['unmatched_total'] > 0)
                        <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle mr-1"></i>
                                Sebagian scan tidak memiliki data karyawan
                            </h6>
                            <p class="mb-1">
                                Scan bulan ini: <strong>{{ number_format($diagnostics['total_scans']) }}</strong> baris,
                                <strong>{{ number_format($diagnostics['unique_pins']) }}</strong> pin unik.
                                Match ke master <code>BIODATA</code>: <strong>{{ number_format($diagnostics['matched_pins']) }}</strong>,
                                tidak match: <strong>{{ number_format($diagnostics['unmatched_total']) }}</strong>.
                            </p>
                            <p class="mb-0 small">
                                Contoh pin tidak match:
                                @foreach($diagnostics['unmatched_pins'] as $pin)
                                    <code class="mr-1">{{ $pin }}</code>
                                @endforeach
                            </p>
                        </div>
                    @endif
                @endif

                <!-- Card Table Rekapitulasi -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 bg-white d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar-alt mr-1"></i> Log Presensi (Wajah + Fingerprint)
                        </h6>
                        <span class="badge badge-primary px-3 py-1 font-weight-bold">
                            {{ count($groupedAll) }} Karyawan
                        </span>
                    </div>
                    <div class="card-body">

                        <!-- Tampilan Desktop (DataTables) -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-hover table-rekap" id="tableRekapAdmin" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="4%" class="text-center">#</th>
                                        <th width="20%">Karyawan</th>
                                        <th width="14%">Departemen</th>
                                        <th width="10%" class="text-center">NPK</th>
                                        <th width="10%" class="text-center">Hari Kerja</th>
                                        <th width="14%" class="text-center">Mode Presensi</th>
                                        <th width="12%" class="text-center">Total Scan</th>
                                        <th width="10%" class="text-center">Detail</th>
                                        <th width="6%" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $no = 1; @endphp
                                    @forelse($groupedAll as $emp)
                                    <tr>
                                        <td class="text-center font-weight-bold">{{ $no++ }}</td>
                                        <td>
                                            <span class="font-weight-bold text-gray-900 d-block">{{ $emp['name'] }}</span>
                                            <small class="text-muted">Barcode: {{ $emp['barcode'] }}</small>
                                        </td>
                                        <td>{{ $emp['dept'] }}</td>
                                        <td class="text-center">{{ $emp['npk'] }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-primary px-2 py-1">{{ $emp['days'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($emp['face_days'] > 0)
                                                <span class="badge badge-primary px-2 py-1 mr-1">
                                                    <i class="fas fa-user-check mr-1"></i> Wajah: {{ $emp['face_days'] }}
                                                </span>
                                            @endif
                                            @if($emp['finger_days'] > 0)
                                                <span class="badge badge-info px-2 py-1 mr-1">
                                                    <i class="fas fa-fingerprint mr-1"></i> Finger: {{ $emp['finger_days'] }}
                                                </span>
                                            @endif
                                            @if($emp['face_days'] == 0 && $emp['finger_days'] == 0)
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-secondary px-2 py-1">{{ $emp['total_scans'] }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if(!empty($emp['face_details']))
                                                <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3"
                                                        data-toggle="modal" data-target="#modalFaceDetail"
                                                        data-npk="{{ $emp['npk'] }}"
                                                        data-emp-name="{{ $emp['name'] }}"
                                                        data-date="{{ $emp['grouped_logs'][array_key_first($emp['grouped_logs'])]['date'] ?? '' }}"
                                                        data-date-label="{{ $emp['grouped_logs'][array_key_first($emp['grouped_logs'])]['formatted_date'] ?? '' }}">
                                                    <i class="fas fa-map-marker-alt mr-1"></i> Lihat
                                                </button>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-info rounded-pill shadow-sm px-2 btn-toggle-emp"
                                                    data-target="#detail-emp-{{ $loop->index }}"
                                                    title="Tampilkan rekap harian">
                                                <i class="fas fa-chevron-right table-toggle-icon"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Baris detail per karyawan (SAMA struktur dengan /face-attendance/summary) -->
                                    <tr class="emp-row-detail d-none" id="detail-emp-{{ $loop->index }}">
                                        {{-- CATATAN DataTables 1.13:
                                             Baris detail WAJIB punya 9 node <td> asli (bukan 1 sel colspan="9").
                                             DataTables menghitung sel per baris lewat _fnGetRowElements (td.nextSibling)
                                             lalu _fnCreateTr; sel yang kurang dari jumlah kolom memicu warning
                                             "Incorrect column count" (warning #18).
                                             Karena itu sel pertama berisi tabel detail, dan 8 sel berikutnya
                                             dibuat collapsed (tanpa padding/border) supaya tampilan tetap
                                             selebar 9 kolom. --}}
                                        <td style="padding:0;border:0;width:100%;">
                                            <div class="table-responsive pt-2 pb-2">
                                                <table class="table table-sm table-bordered table-rekap" style="background:#fff;">
                                                    <thead>
                                                        <tr>
                                                            <th width="5%" class="text-center">#</th>
                                                            <th width="18%">Tanggal & Hari</th>
                                                            <th width="16%" class="text-center">Jam Masuk (Clock In)</th>
                                                            <th width="16%" class="text-center">Jam Pulang (Clock Out)</th>
                                                            <th width="16%" class="text-center">Mode Presensi</th>
                                                            <th width="13%" class="text-center">Status</th>
                                                            <th width="16%" class="text-center">Detail</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $dno = 1; @endphp
                                                        @foreach($emp['grouped_logs'] as $log)
                                                        <tr>
                                                            <td class="text-center font-weight-bold">{{ $dno++ }}</td>
                                                            <td>
                                                                <span class="font-weight-bold text-gray-900 d-block">{{ $log['formatted_date'] }}</span>
                                                                <small class="text-muted">{{ $log['day_name'] }}</small>
                                                            </td>
                                                            <td class="text-center">
                                                                @if($log['clock_in'])
                                                                    <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                                                        <i class="fas fa-sign-in-alt mr-1"></i> {{ $log['clock_in'] }}
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                @if($log['clock_out'])
                                                                    <span class="badge badge-warning px-3 py-2 font-weight-bold text-dark" style="font-size: 0.85rem;">
                                                                        <i class="fas fa-sign-out-alt mr-1"></i> {{ $log['clock_out'] }}
                                                                    </span>
                                                                @else
                                                                    <span class="badge badge-light border text-muted px-2 py-1">Belum Melakukan Scan</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                @foreach($log['modes'] as $mode)
                                                                    <span class="badge badge-{{ $mode == 'Wajah' ? 'primary' : 'info' }} px-2 py-1 mr-1">
                                                                        <i class="fas {{ $mode == 'Wajah' ? 'fa-user-check' : 'fa-fingerprint' }} mr-1"></i> {{ $mode }}
                                                                    </span>
                                                                @endforeach
                                                            </td>
                                                            <td class="text-center">
                                                                @if($log['clock_in'] && $log['clock_out'])
                                                                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-double mr-1"></i> Lengkap</span>
                                                                @elseif($log['clock_in'])
                                                                    <span class="badge badge-info px-2 py-1"><i class="fas fa-sign-in-alt mr-1"></i> Clock In Saja</span>
                                                                @else
                                                                    <span class="badge badge-warning px-2 py-1"><i class="fas fa-sign-out-alt mr-1"></i> Clock Out Saja</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-center">
                                                                @if(!empty($emp['face_details']))
                                                                    <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-3"
                                                                            data-toggle="modal" data-target="#modalFaceDetail"
                                                                            data-npk="{{ $emp['npk'] }}"
                                                                            data-emp-name="{{ $emp['name'] }}"
                                                                            data-date="{{ $log['date'] }}"
                                                                            data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                                                        <i class="fas fa-map-marker-alt mr-1"></i> Lihat
                                                                    </button>
                                                                @else
                                                                    <span class="text-muted small">-</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                        {{-- 8 sel collapsed: menjaga jumlah node <td> = 9 kolom
                                             agar DataTables tidak memunculkan warning
                                             "Incorrect column count". --}}
                                        @for($c = 1; $c < 9; $c++)
                                        <td style="padding:0;border:0;"></td>
                                        @endfor
                                    </tr>
                                    @empty
                                    {{-- DataTables 1.13 menghitung sel per baris lewat td.nextSibling,
                                         jadi baris kosong tetap WAJIB punya 9 node <td> asli
                                         agar tidak muncul warning "Incorrect column count". --}}
                                    <tr class="empty-state-row">
                                        <td colspan="9" class="text-center py-5 text-muted border-0">
                                            <i class="fas fa-info-circle fa-2x mb-2 d-block text-secondary"></i>
                                            <span class="d-block font-weight-bold text-gray-700">Belum ada data presensi pada bulan ini.</span>
                                            <small class="text-muted">Coba ubah filter bulan / departemen, atau pastikan karyawan sudah melakukan presensi.</small>
                                        </td>
                                        @for($c = 1; $c < 9; $c++)
                                        <td class="d-none"></td>
                                        @endfor
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Tampilan Mobile (Card List) -->
                        <div class="d-block d-md-none">
                            @forelse($groupedAll as $emp)
                            <div class="card mb-3 border-left-{{ $emp['face_days'] > 0 ? 'primary' : 'info' }} shadow-sm">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="font-weight-bold text-gray-900">{{ $emp['name'] }}</span>
                                        <span class="badge badge-primary">{{ $emp['days'] }} Hari</span>
                                    </div>
                                    <div class="small text-muted mb-2">
                                        NPK: {{ $emp['npk'] }} &middot; {{ $emp['dept'] }} &middot; {{ $emp['total_scans'] }} scan
                                    </div>
                                    <div class="mb-2">
                                        @if($emp['face_days'] > 0)
                                            <span class="badge badge-primary px-2 py-1 mr-1"><i class="fas fa-user-check mr-1"></i> Wajah: {{ $emp['face_days'] }}</span>
                                        @endif
                                        @if($emp['finger_days'] > 0)
                                            <span class="badge badge-info px-2 py-1 mr-1"><i class="fas fa-fingerprint mr-1"></i> Finger: {{ $emp['finger_days'] }}</span>
                                        @endif
                                    </div>
                                    <div class="border-top pt-2">
                                        @foreach($emp['grouped_logs'] as $log)
                                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                            <span class="small">{{ $log['formatted_date'] }} <span class="text-muted">({{ $log['day_name'] }})</span></span>
                                            <span class="small">
                                                <span class="text-success font-weight-bold">{{ $log['clock_in'] ?? '-' }}</span>
                                                <span class="text-muted">/</span>
                                                <span class="text-warning font-weight-bold">{{ $log['clock_out'] ?? '-' }}</span>
                                                @if(!empty($emp['face_details']))
                                                    <button type="button" class="btn btn-sm btn-primary rounded-pill shadow-sm px-2 ml-1"
                                                            data-toggle="modal" data-target="#modalFaceDetail"
                                                            data-npk="{{ $emp['npk'] }}"
                                                            data-emp-name="{{ $emp['name'] }}"
                                                            data-date="{{ $log['date'] }}"
                                                            data-date-label="{{ $log['formatted_date'] }} ({{ $log['day_name'] }})">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </button>
                                                @endif
                                            </span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle fa-2x mb-2 d-block text-secondary"></i>
                                Belum ada data presensi pada bulan ini.
                            </div>
                            @endforelse
                        </div>

                    </div>
                </div>
