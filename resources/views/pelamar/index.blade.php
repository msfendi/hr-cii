<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    <!-- Page Wrapper -->
    @include('sweetalert::alert')
    <div id="wrapper">
        @include('layout.sidebar')
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">
                @include('layout.navbar')
                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Pelamar</h1>
                    </div>

                    <!-- DataTales Example -->
                    <div class="card shadow mb-2">
                        <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Pelamar</h6>
                            <div>
                                <a href="{{ route('pelamar.template') }}" class="btn btn-info btn-sm mr-2">
                                    <i class="fas fa-file-download"></i> Template Excel
                                </a>
                                <button type="button" class="btn btn-success btn-sm" data-toggle="modal"
                                    data-target="#importModal">
                                    <i class="fas fa-file-import"></i> Import Excel
                                </button>
                            </div>
                        </div>

                        <!-- Import Modal -->
                        <div class="modal fade" id="importModal" tabindex="-1" role="dialog"
                            aria-labelledby="importModalLabel" aria-hidden="true">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="importModalLabel">Import Pelamar Data</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <form action="{{ route('pelamar.import') }}" method="POST"
                                        enctype="multipart/form-data">
                                        @csrf
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="file">Choose Excel File</label>
                                                <input type="file" class="form-control-file" id="file" name="file"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary"
                                                data-dismiss="modal">Close</button>
                                            <button type="submit" class="btn btn-primary">Import</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="dataTable" width="100%"
                                    cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th width="80px">NPK</th>
                                            <th width="200px">NAMA</th>
                                            <th>JENIS KELAMIN</th>
                                            <th>TEMPAT LAHIR</th>
                                            <th>TGL LAHIR</th>
                                            <th>TMK</th>
                                            <th>UMUR</th>
                                            <th>NIK</th>
                                            <th>KABUPATEN</th>
                                            <th>HP</th>
                                            <th>ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pelamars as $pelamar)
                                            <tr>
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $pelamar->NPK }}</td>
                                                <td>{{ $pelamar->NAMA }}</td>
                                                <td>{{ $pelamar->JENIS_KELAMIN == 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                                                <td>{{ $pelamar->TMPT_LAHIR }}</td>
                                                <td>{{ $pelamar->TGL_LAHIR }}</td>
                                                <td>{{ $pelamar->TMK }}</td>
                                                <td>{{ $pelamar->UMUR }}</td>
                                                <td>{{ $pelamar->NIK }}</td>
                                                <td>{{ $pelamar->KABUPATEN }}</td>
                                                <td>{{ $pelamar->HP }}</td>
                                                <td>
                                                    <button class="btn btn-sm btn-info btn-assign"
                                                        data-id="{{ $pelamar->ID }}" data-toggle="modal"
                                                        data-target="#assignModal">
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Content Row -->

                    <!-- Assign Modal -->
                    <div class="modal fade" id="assignModal" tabindex="-1" role="dialog"
                        aria-labelledby="assignModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl" style="max-width: 80%; width: 80%;" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="assignModalLabel">Detail Pelamar & Assign</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form action="{{ route('pelamar.assign') }}" method="POST">
                                    @csrf
                                    <div class="modal-body">
                                        <input type="hidden" name="id_pelamar" id="assign_id_pelamar">

                                        <div class="row px-3">
                                            {{-- 1st Column: Personal Information --}}
                                            <div class="col-md-6">
                                                <h6 class="font-weight-bold text-primary mb-3">Personal Information</h6>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">NPK</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_npk"
                                                            name="npk">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Nama</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_nama"
                                                            name="nama">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">NIK</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_nik"
                                                            name="nik">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">No KK</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_kk"
                                                            name="no_kk">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Jenis Kelamin</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_jk"
                                                            name="jk">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Tempat Lahir</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_tempat_lahir" name="tempat_lahir">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Tgl Lahir</label>
                                                    <div class="col-sm-8">
                                                        <input type="date" class="form-control py-0"
                                                            id="assign_tgl_lahir" name="tgl_lahir">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Umur</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_umur"
                                                            name="umur">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Agama</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_agama"
                                                            name="agama">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Status</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_status"
                                                            name="status">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Nama Ibu</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_ibu"
                                                            name="ibu">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Tanggungan</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_tanggungan" name="tanggungan">
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- 2nd Column: Contact & Education --}}
                                            <div class="col-md-6">
                                                <h6 class="font-weight-bold text-primary mb-3">Contact & Education</h6>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">No HP</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_hp"
                                                            name="hp">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Alamat</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_alamat"
                                                            name="alamat" placeholder="Alamat Lengkap">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Kabupaten</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_kabupaten" name="kabupaten"
                                                            placeholder="Kabupaten">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Domisili</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_domisili" name="domisili"
                                                            placeholder="Alamat Domisili">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Pendidikan</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_pendidikan" name="pendidikan">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Sekolah/Univ</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_sekolah"
                                                            name="sekolah">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Kab. Sekolah</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0"
                                                            id="assign_kabupaten_sekolah" name="kabupaten_sekolah">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">Jurusan</label>
                                                    <div class="col-sm-8">
                                                        <input type="text" class="form-control py-0" id="assign_jurusan"
                                                            name="jurusan">
                                                    </div>
                                                </div>
                                                <div class="form-group row mb-1">
                                                    <label class="col-sm-4 col-form-label py-0">TB / BB</label>
                                                    <div class="col-sm-8 input-group">
                                                        <input type="number" class="form-control py-0" id="assign_tb"
                                                            name="tb" placeholder="TB">
                                                        <div class="input-group-append"><span
                                                                class="input-group-text py-0">cm</span></div>
                                                        <input type="number" class="form-control py-0" id="assign_bb"
                                                            name="bb" placeholder="BB">
                                                        <div class="input-group-append"><span
                                                                class="input-group-text py-0">kg</span></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-3">

                                        {{-- 3rd Section: Employment Assignment --}}
                                        <div class="px-3">
                                            <h6 class="font-weight-bold text-success mb-3">Assign Employee</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group row mb-1">
                                                        <label class="col-sm-4 col-form-label py-0">Department <span
                                                                class="text-danger">*</span></label>
                                                        <div class="col-sm-8">
                                                            <select class="form-control py-0" id="assign_id_dept"
                                                                name="id_dept" required>
                                                                <option value="">-- Pilih --</option>
                                                                @foreach($departments as $dept)
                                                                    <option value="{{ $dept->ID_DEPT }}">
                                                                        {{ $dept->DEPARTEMENT }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-1">
                                                        <label class="col-sm-4 col-form-label py-0">Section <span
                                                                class="text-danger">*</span></label>
                                                        <div class="col-sm-8">
                                                            <select class="form-control py-0" id="assign_section"
                                                                name="section" required>
                                                                <option value="">-- Pilih --</option>
                                                                @foreach($sections as $section)
                                                                    <option value="{{ $section->name }}">
                                                                        {{ $section->name }} - ( {{ $section->line_start }}
                                                                        - {{ $section->line_end }} )
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-1">
                                                        <label class="col-sm-4 col-form-label py-0">TMK <span
                                                                class="text-danger">*</span></label>
                                                        <div class="col-sm-8">
                                                            <input type="date" class="form-control py-0" id="assign_tmk"
                                                                name="tmk" required>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-1">
                                                        <label class="col-sm-4 col-form-label py-0">Durasi
                                                            Kontrak</label>
                                                        <div class="col-sm-8">
                                                            <select class="form-control py-0" id="assign_duration"
                                                                name="month_duration">
                                                                <option value="1">1 Bulan</option>
                                                                <option value="2">2 Bulan</option>
                                                                <option value="3">3 Bulan</option>
                                                                <option value="4">4 Bulan</option>
                                                                <option value="5">5 Bulan</option>
                                                                <option value="6">6 Bulan</option>
                                                                <option value="7">7 Bulan</option>
                                                                <option value="8">8 Bulan</option>
                                                                <option value="9">9 Bulan</option>
                                                                <option value="10">10 Bulan</option>
                                                                <option value="11">11 Bulan</option>
                                                                <option value="12">12 Bulan</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-1">
                                                        <label class="col-sm-4 col-form-label py-0">Tgl Akhir
                                                            Kontrak</label>
                                                        <div class="col-sm-8">
                                                            <input type="text" class="form-control py-0"
                                                                id="assign_end_date_display" readonly
                                                                placeholder="Auto">
                                                            <input type="hidden" id="assign_end_date" name="end_date">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group row mb-2">
                                                        <label class="col-sm-4 col-form-label">Gaji Pokok</label>
                                                        <div class="col-sm-8">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend"><span
                                                                        class="input-group-text">Rp</span></div>
                                                                <input type="text" class="form-control"
                                                                    id="assign_salary" name="salary" value="2.500.000"
                                                                    inputmode="numeric">
                                                                <input type="hidden" id="assign_salary_raw"
                                                                    name="salary_raw" value="2500000">
                                                            </div>
                                                            <small class="text-muted" style="font-size: 70%;">Default:
                                                                Rp 2.500.000</small>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-2">
                                                        <label class="col-sm-4 col-form-label">Tunjangan</label>
                                                        <div class="col-sm-8">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend"><span
                                                                        class="input-group-text">Rp</span></div>
                                                                <input type="text" class="form-control"
                                                                    id="assign_allowance" name="allowance" value="0"
                                                                    inputmode="numeric">
                                                                <input type="hidden" id="assign_allowance_raw"
                                                                    name="allowance_raw" value="0">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-2">
                                                        <label class="col-sm-4 col-form-label">PPH21</label>
                                                        <div class="col-sm-8">
                                                            <div class="input-group">
                                                                <div class="input-group-prepend"><span
                                                                        class="input-group-text">Rp</span></div>
                                                                <input type="text" class="form-control"
                                                                    id="assign_pph21" name="pph21" value="0"
                                                                    inputmode="numeric">
                                                                <input type="hidden" id="assign_pph21_raw"
                                                                    name="pph21_raw" value="0">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group row mb-2 align-items-center">
                                                        <label class="col-sm-4 col-form-label">Status Staff</label>
                                                        <div class="col-sm-8">
                                                            <div class="custom-control custom-switch mt-1"
                                                                style="transform: scale(1.25); transform-origin: left center;">
                                                                <input type="checkbox" class="custom-control-input"
                                                                    id="assign_is_staff" name="is_staff">
                                                                <label
                                                                    class="custom-control-label font-weight-bold text-primary"
                                                                    for="assign_is_staff"
                                                                    style="cursor: pointer; user-select: none;">
                                                                    Tandai sebagai Staff
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-success assign-save">Assign & Save</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->

            </div>
            <!-- End of Main Content -->
            @include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
{{--
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css"
    rel="stylesheet" />

<script>
    // ── IDR Format Helpers ───────────────────────────────────────
    function formatIdr(val) {
        const num = String(val).replace(/\D/g, '');
        return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function parseIdr(str) {
        return parseFloat(String(str).replace(/\./g, '')) || 0;
    }

    function bindSalaryInput(displayId, rawId) {
        const $d = $('#' + displayId);
        const $r = $('#' + rawId);
        $d.on('input', function () {
            const raw = parseIdr(this.value);
            $r.val(raw);
            const pos = this.selectionStart;
            this.value = formatIdr(raw);
        });
        $d.on('blur', function () {
            const raw = parseIdr(this.value);
            $r.val(raw);
            this.value = formatIdr(raw);
        });
    }

    // ── Auto calc end date ───────────────────────────────────────
    function recalcEndDate() {
        const tmk = $('#assign_tmk').val();
        const dur = parseInt($('#assign_duration').val());
        if (!tmk) { $('#assign_end_date_display').val(''); $('#assign_end_date').val(''); return; }

        const parts = tmk.split('-');
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        d.setMonth(d.getMonth() + dur);
        d.setDate(d.getDate() - 1);

        const y = d.getFullYear();
        const m = d.getMonth();

        const options = [
            new Date(y, m - 1, 20),
            new Date(y, m, 7),
            new Date(y, m, 20),
            new Date(y, m + 1, 7)
        ];

        let closestDate = options[0];
        let minDiff = Math.abs(d - closestDate);

        for (let i = 1; i < options.length; i++) {
            const diff = Math.abs(d - options[i]);
            if (diff < minDiff) {
                minDiff = diff;
                closestDate = options[i];
            }
        }

        const dd = String(closestDate.getDate()).padStart(2, '0');
        const mm = String(closestDate.getMonth() + 1).padStart(2, '0');
        const yyyy = closestDate.getFullYear();
        const iso = `${yyyy}-${mm}-${dd}`;

        $('#assign_end_date').val(iso);
        $('#assign_end_date_display').val(closestDate.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' }));
    }

    $(document).ready(function () {
        // Init salary formatting
        bindSalaryInput('assign_salary', 'assign_salary_raw');
        bindSalaryInput('assign_allowance', 'assign_allowance_raw');
        bindSalaryInput('assign_pph21', 'assign_pph21_raw');

        // Set default display
        $('#assign_salary').val(formatIdr(2500000));
        $('#assign_salary_raw').val(2500000);
        $('#assign_allowance').val(formatIdr(0));
        $('#assign_allowance_raw').val(0);
        $('#assign_pph21').val(formatIdr(0));
        $('#assign_pph21_raw').val(0);

        // Auto end date
        $('#assign_tmk, #assign_duration').on('change', recalcEndDate);

        // Sync raws before form submit
        $('form[action*="assign"]').on('submit', function () {
            $('#assign_salary_raw').val(parseIdr($('#assign_salary').val()));
            $('#assign_allowance_raw').val(parseIdr($('#assign_allowance').val()));
            $('#assign_pph21_raw').val(parseIdr($('#assign_pph21').val()));
        });

        // ── Assign modal: load detail ────────────────────────────
        $(document).on('click', '.btn-assign', function () {
            var id = $(this).data('id');
            $('#assign_id_pelamar').val(id);

            // Reset
            $('#assign_npk').val('Loading...');
            $('#assign_nama,#assign_nik,#assign_kk,#assign_tempat_lahir,#assign_tgl_lahir').val('');
            $('#assign_umur,#assign_agama,#assign_status,#assign_ibu,#assign_tanggungan').val('');
            $('#assign_hp,#assign_alamat,#assign_kabupaten,#assign_domisili').val('');
            $('#assign_pendidikan,#assign_sekolah,#assign_kabupaten_sekolah,#assign_jurusan').val('');
            $('#assign_tb,#assign_bb,#assign_tmk').val('');
            $('#assign_end_date').val('');
            $('#assign_end_date_display').val('');
            // Reset salary defaults
            $('#assign_salary').val(formatIdr(2500000));
            $('#assign_salary_raw').val(2500000);
            $('#assign_allowance').val(formatIdr(0));
            $('#assign_allowance_raw').val(0);
            $('#assign_pph21').val(formatIdr(0));
            $('#assign_pph21_raw').val(0);

            $.ajax({
                url: '/pelamar/detail/' + id,
                type: 'GET',
                success: function (response) {
                    $('#assign_npk').val(response.NPK);
                    $('#assign_nama').val(response.NAMA);
                    $('#assign_nik').val(response.NIK);
                    $('#assign_kk').val(response.NO_KK);
                    $('#assign_jk').val(response.JENIS_KELAMIN);
                    $('#assign_tempat_lahir').val(response.TMPT_LAHIR);
                    $('#assign_tgl_lahir').val(response.TGL_LAHIR);
                    $('#assign_umur').val(response.UMUR);
                    $('#assign_agama').val(response.AGAMA);
                    $('#assign_status').val(response.STATUS);
                    $('#assign_ibu').val(response.IBU);
                    $('#assign_tanggungan').val(response.TANGGUNGAN);
                    $('#assign_hp').val(response.HP);
                    $('#assign_alamat').val(response.ALAMAT_LENGKAP);
                    $('#assign_kabupaten').val(response.KABUPATEN);
                    $('#assign_domisili').val(response.ALAMAT_DOMISILI);
                    $('#assign_pendidikan').val(response.PENDIDIKAN);
                    $('#assign_sekolah').val(response.NAMA_SEKOLAH);
                    $('#assign_kabupaten_sekolah').val(response.KABUPATEN_SEKOLAH);
                    $('#assign_jurusan').val(response.JURUSAN);
                    $('#assign_tb').val(response.TINGGI_BADAN);
                    $('#assign_bb').val(response.BERAT_BADAN);
                    if (response.TMK) {
                        $('#assign_tmk').val(response.TMK);
                        recalcEndDate();
                    }
                },
                error: function (xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data pelamar');
                }
            });
        });
    });
</script>

</html>