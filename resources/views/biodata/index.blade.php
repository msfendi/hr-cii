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
                    <h1 class="h3 mb-0 text-gray-800">BIODATA</h1>
                </div>
                
                <!-- Add Button in Card Header -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3 d-sm-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">BIODATA</h6>
                        <button type="button" class="btn btn-primary btn-sm btn-add">
                            <i class="fas fa-plus"></i> Add Employee
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>NAMA_KARYAWAN</th>
                                        <th>BAG</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($biodatas as $biodata)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $biodata->NPK }}</td>
                                        <td>{{ $biodata->NAMA_KARYAWAN }}</td>
                                        <td>{{ $biodata->BAG }}</td>
                                        <td>
                                            <a class="btn btn-primary btn-sm btn-show" data-npk="{{ $biodata->NPK }}"
                                                data-toggle="modal" 
                                                data-target="#showModal">Show</a>
                                            <a href="{{ route('biodata.edit', $biodata->NPK) }}" class="btn btn-warning btn-sm">Edit</a>
                                            <a href="{{ route('biodata.destroy', $biodata->NPK) }}" class="btn btn-danger btn-sm">Delete</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

                <!-- Add Modal -->
                <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
                        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
                            <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                                <h5 class="modal-title font-weight-bold ml-2" id="addModalLabel">Tambah Karyawan</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            
                            <form action="{{ route('biodata.store') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body p-4">
                                    <div class="row">
                                        <!-- Left Side: Profile & Identity (Sticky) -->
                                        <div class="col-lg-2 border-right">
                                            <div class="d-flex flex-column align-items-center text-center p-3">
                                                <!-- Photo Upload -->
                                                <div class="position-relative mb-4">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 140px; height: 140px; border: 4px solid white; overflow: hidden;">
                                                        <i class="fas fa-camera text-secondary fa-2x" id="cameraIcon"></i>
                                                        <img id="previewImage" src="" alt="Preview" class="w-100 h-100 d-none" style="object-fit: cover;">
                                                    </div>
                                                    <div class="custom-file position-absolute w-100 h-100" style="top: 0; left: 0; opacity: 0; cursor: pointer;">
                                                        <input type="file" class="custom-file-input h-100" id="fotoInput" name="foto_profil" style="cursor: pointer;" onchange="document.getElementById('previewImage').src = window.URL.createObjectURL(this.files[0]); document.getElementById('previewImage').classList.remove('d-none'); document.getElementById('cameraIcon').classList.add('d-none');">
                                                    </div>
                                                    <div class="small mt-2 text-primary font-weight-bold" style="cursor: pointer;">Change Photo</div>
                                                </div>

                                                <!-- Key Identity Fields -->
                                                <div class="w-100 text-left">
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">NPK <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control px-3" name="npk" required placeholder="Ex: 12345">
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Nama Lengkap <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control px-3" name="nama" required placeholder="Employee Name">
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Jenis Kelamin</label>
                                                        <select class="form-control px-3" name="jk" required style="height: 38px;">
                                                            <option value="" selected disabled>Pilih Jenis Kelamin</option>
                                                            <option value="L">Laki-laki</option>
                                                            <option value="P">Perempuan</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Status Menikah</label>
                                                        <select class="form-control px-3" name="status" style="height: 38px;">
                                                            <option value="" selected disabled>Pilih Status Menikah</option>
                                                            <option value="BM">Belum Menikah</option>
                                                            <option value="M">Menikah</option>
                                                            <option value="CH">Cerai Hidup</option>
                                                            <option value="CM">Cerai Mati</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Right Side: Details Form -->
                                        <div class="col-lg-10 pl-lg-4 pt-3">
                                            <div class="row">
                                                <!-- Personal Info -->
                                                <div class="col-12 mb-4">
                                                    <h6 class="font-weight-bold text-primary mb-3 pl-2 border-left-primary ml-1">&nbsp;Informasi Pribadi</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">NIK</label>
                                                            <input type="text" class="form-control px-3" name="nik">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Nomor KK</label>
                                                            <input type="text" class="form-control px-3" name="nkk">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Nama Ibu</label>
                                                            <input type="text" class="form-control px-3" name="ibu">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Tempat Lahir</label>
                                                            <input type="text" class="form-control px-3" name="tempat_lahir">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Tanggal Lahir</label>
                                                            <input type="date" class="form-control px-3" name="tgl_lahir">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Agama</label>
                                                            <select class="form-control px-3" name="agama" style="height: 38px;">
                                                                <option value="" selected disabled>Select</option>
                                                                <option value="Islam">Islam</option>
                                                                <option value="Kristen">Kristen</option>
                                                                <option value="Katolik">Katolik</option>
                                                                <option value="Hindu">Hindu</option>
                                                                <option value="Buddha">Buddha</option>
                                                                <option value="Lainnya">Others</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Contact -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="font-weight-bold text-info mb-3 pl-2 border-left-info ml-1">&nbsp;Kontak & Alamat</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">No. HP</label>
                                                            <input type="text" class="form-control px-3" name="hp">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Kabupaten</label>
                                                            <input type="text" class="form-control px-3" name="kabupaten">
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Alamat</label>
                                                        <textarea class="form-control rounded-lg px-3 py-2" name="alamat" rows="2" style="border-radius: 5px;"></textarea>
                                                    </div>
                                                    <div class="row">
                                                         <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Umur</label>
                                                            <input type="number" class="form-control px-3" name="umur" placeholder="Ex: 25">
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Employment & Education -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="font-weight-bold text-success mb-3 pl-2 border-left-success ml-1">&nbsp;Pendidikan & Pekerjaan</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Department <span class="text-danger">*</span></label>
                                                            <select class="form-control px-3" name="id_dept" required>
                                                                <option value="">-- Pilih Department --</option>
                                                                @foreach($departments as $department)
                                                                    <option value="{{ $department->ID_DEPT }}">{{ $department->DEPARTEMENT }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                             <label class="small font-weight-bold text-muted ml-2">TMK <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control px-3" name="tmk" required>
                                                        </div>
                                                        <div class="col-md-12 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Sekolah/Universitas</label>
                                                            <input type="text" class="form-control px-3" name="sekolah" placeholder="Nama Sekolah/Kampus">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Jenjang Pendidikan</label>
                                                            <select class="form-control px-3" name="pendidikan" style="height: 38px;">
                                                                <option value="" selected disabled>Select</option>
                                                                <option value="SD">SD</option>
                                                                <option value="SMP">SMP</option>
                                                                <option value="SMA">SMA</option>
                                                                <option value="SMK">SMK</option>
                                                                <option value="D1">D1</option>
                                                                <option value="D2">D2</option>
                                                                <option value="D3">D3</option>
                                                                <option value="D4">D4</option>
                                                                <option value="S1">S1</option>
                                                                <option value="S2">S2</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Jurusan</label>
                                                            <input type="text" class="form-control px-3" name="jurusan">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Jumlah Tanggungan</label>
                                                            <input type="number" class="form-control px-3" name="tanggungan" placeholder="0">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pb-4 pr-5 bg-white">
                                    <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary px-5 font-weight-bold shadow-sm">Tambah Karyawan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- show Modal -->
                <!-- Show Modal -->
                <div class="modal fade" id="showModal" tabindex="-1" role="dialog" aria-labelledby="showModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
                        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
                            <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                                <h5 class="modal-title font-weight-bold ml-2" id="showModalLabel">Detail Employee</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            
                            <div class="modal-body p-4">
                                <input type="hidden" name="id_pelamar" id="show_id_pelamar">
                                
                                <div class="row">
                                    <!-- Left Side: Profile & Identity (Sticky) -->
                                    <div class="col-lg-2 border-right">
                                        <div class="d-flex flex-column align-items-center text-center p-3">
                                            <!-- Photo Display -->
                                            <div class="position-relative mb-4">
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 140px; height: 140px; border: 4px solid white; overflow: hidden;">
                                                    <img id="show_foto_profil" src="" alt="Profile" class="w-100 h-100" style="object-fit: cover;">
                                                </div>
                                            </div>

                                            <!-- Key Identity Fields -->
                                            <div class="w-100 text-left">
                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-muted ml-2">NPK</label>
                                                    <input type="text" class="form-control px-3" id="show_npk" readonly>
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-muted ml-2">Full Name</label>
                                                    <input type="text" class="form-control px-3" id="show_nama" readonly>
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-muted ml-2">Gender</label>
                                                    <input type="text" class="form-control px-3" id="show_jk" readonly>
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label class="small font-weight-bold text-muted ml-2">Marital Status</label>
                                                    <input type="text" class="form-control px-3" id="show_status" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Side: Details Form -->
                                    <div class="col-lg-10 pl-lg-4 pt-3">
                                        <div class="row">
                                            <!-- Personal Info -->
                                            <div class="col-12 mb-4">
                                                <h6 class="font-weight-bold text-primary mb-3 pl-2 border-left-primary ml-1">&nbsp;Personal Information</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">NIK Identity</label>
                                                        <input type="text" class="form-control px-3" id="show_nik" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Family Card (KK)</label>
                                                        <input type="text" class="form-control px-3" id="show_nkk" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Mother's Name</label>
                                                        <input type="text" class="form-control px-3" id="show_ibu" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Place of Birth</label>
                                                        <input type="text" class="form-control px-3" id="show_tempat_lahir" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Date of Birth</label>
                                                        <input type="text" class="form-control px-3" id="show_tgl_lahir" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Religion</label>
                                                        <input type="text" class="form-control px-3" id="show_agama" readonly>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Contact -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="font-weight-bold text-info mb-3 pl-2 border-left-info ml-1">&nbsp;Contact & Address</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Phone</label>
                                                        <input type="text" class="form-control px-3" id="show_hp" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">City</label>
                                                        <input type="text" class="form-control px-3" id="show_kabupaten" readonly>
                                                    </div>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="small font-weight-bold text-muted ml-2">Address</label>
                                                    <textarea class="form-control rounded-lg px-3 py-2" id="show_alamat" rows="2" style="border-radius: 5px;" readonly></textarea>
                                                </div>
                                                <div class="row">
                                                     <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Age</label>
                                                        <input type="text" class="form-control px-3" id="show_umur" readonly>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Employment & Education -->
                                            <div class="col-md-6 mb-3">
                                                <h6 class="font-weight-bold text-success mb-3 pl-2 border-left-success ml-1">&nbsp;Employment & Education</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Dept</label>
                                                        <input type="text" class="form-control px-3" id="show_id_dept" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                         <label class="small font-weight-bold text-muted ml-2">TMK</label>
                                                        <input type="text" class="form-control px-3" id="show_tmk" readonly>
                                                    </div>
                                                    <div class="col-md-12 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">School/University</label>
                                                        <input type="text" class="form-control px-3" id="show_sekolah" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Education</label>
                                                        <input type="text" class="form-control px-3" id="show_pendidikan" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Major</label>
                                                        <input type="text" class="form-control px-3" id="show_jurusan" readonly>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Dependents</label>
                                                        <input type="text" class="form-control px-3" id="show_tanggungan" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-0 pb-4 pr-5 bg-white">
                                <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal">Close</button>
                            </div>
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
{{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        $('body').on('click', '.btn-show', function() {
            var npk = $(this).data('npk');
            console.log(npk);
            $('#show_npk').val(npk);

            // Clear previous data
            $('#show_npk').val('Loading...');
            $('#show_nama').val('');
            $('#show_nik').val('');
            $('#show_nkk').val('');
            $('#show_ibu').val('');
            $('#show_jk').val('');
            $('#show_tempat_lahir').val('');
            $('#show_tgl_lahir').val('');
            $('#show_umur').val('');
            $('#show_agama').val('');
            $('#show_status').val('');
            $('#show_hp').val('');
            $('#show_alamat').text('');
            $('#show_kabupaten').text('');
            $('#show_pendidikan').val('');
            $('#show_sekolah').val('');
            $('#show_jurusan').val('');
            $('#show_id_dept').text('');
            $('#show_tmk').text('');
            $('#show_tanggungan').text('');

            $.ajax({
                url: '/biodata/show/' + npk,
                type: 'GET',
                success: function(response) {
                    $('#show_npk').val(response.NPK);
                    $('#show_nama').val(response.NAMA);
                    $('#show_nik').val(response.KTP ?? 'NIK TIDAK DIISI');
                    $('#show_nkk').val(response.NO_KK ?? 'NOMOR KK TIDAK DIISI');
                    $('#show_ibu').val(response.IBU ?? 'IBU TIDAK DIISI');
                    $('#show_jk').val(response.JK == 'L' ? 'LAKI LAKI' : 'PEREMPUAN');
                    $('#show_tempat_lahir').val(response.TMPTLAHIR ?? 'TEMPAT LAHIR TIDAK DIISI');
                    $('#show_tgl_lahir').val(response.TGLLAHIR ?? 'TANGGAL LAHIR TIDAK DIISI');
                    $('#show_umur').val(response.USIA ?? 'UMUR TIDAK DIISI');
                    $('#show_agama').val(response.AGAMA ?? 'AGAMA TIDAK DIISI');
                    $('#show_status').val(response.STATUS ?? 'STATUS TIDAK DIISI');
                    $('#show_hp').val(response.HP ?? 'HP TIDAK DIISI');
                    $('#show_alamat').val(response.ALAMAT ?? 'ALAMAT TIDAK DIISI');
                    $('#show_kabupaten').val(response.KABUPATEN ?? 'KABUPATEN TIDAK DIISI');
                    $('#show_pendidikan').val(response.PDDK ?? 'PENDIDIKAN TIDAK DIISI');
                    $('#show_sekolah').val(response.NAMA_SEKOLAH ?? 'SEKOLAH TIDAK DIISI');
                    $('#show_jurusan').val(response.JURUSAN ?? 'JURUSAN TIDAK DIISI');
                    $('#show_id_dept').val(response.BAGIAN ?? 'DEPT TIDAK DIISI');
                    $('#show_tmk').val(response.TMK ?? 'TMK TIDAK DIISI');
                    $('#show_tanggungan').val(response.TANGGUNGAN ?? 'TANGGUNGAN TIDAK DIISI');
                    const imageUrl = `/storage/img/profile/${response.NAMA}.jpg`;

                    // Set the image src dynamically
                    const imgElement = document.getElementById('show_foto_profil');
                    imgElement.src = imageUrl;
                
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data pelamar');
                }
            });
        });

        // modal untuk input karyawan
        $('.btn-add').on('click', function() {
            $('#addModal').modal('show');
        });
    });
</script>
</html>