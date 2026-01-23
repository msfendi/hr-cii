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
                        <div>
                            <a href="{{ route('biodata.export') }}" class="btn btn-success btn-sm">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </a>
                            <button type="button" class="btn btn-primary btn-sm btn-add">
                                <i class="fas fa-plus"></i> Add Employee
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>NAMA_KARYAWAN</th>
                                        <th>DEPARTMENT</th>
                                        <th>ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($biodatas as $biodata)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $biodata->NPK }}</td>
                                        <td>{{ $biodata->NAMA_KARYAWAN }}</td>
                                        <td>{{ $biodata->DEPARTEMENT }}</td>
                                        <td>
                                            <a class="btn btn-primary btn-sm btn-show" data-npk="{{ $biodata->NPK }}"
                                                data-toggle="modal" 
                                                data-target="#showModal">Show</a>
                                            <a href="javascript:void(0)" class="btn btn-warning btn-sm btn-edit" data-npk="{{ $biodata->NPK }}">Update Karyawan</a>
                                            <a href="javascript:void(0)" data-id="{{ $biodata->NPK }}" data-nama="{{ $biodata->NAMA_KARYAWAN }}" class="btn btn-danger btn-sm btn-exit">Exit</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->

                <!-- Edit Modal -->
                <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
                        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
                            <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                                <h5 class="modal-title font-weight-bold ml-2" id="editModalLabel">Update Karyawan</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            
                            <form id="editForm" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="modal-body p-4">
                                    <div class="row">
                                        <div class="col-lg-2 border-right">
                                            <div class="d-flex flex-column align-items-center text-center p-3">
                                                <!-- Photo Upload -->
                                                <div class="position-relative mb-4">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 140px; height: 140px; border: 4px solid white; overflow: hidden;">
                                                        <i class="fas fa-camera text-secondary fa-2x d-none" id="edit_cameraIcon"></i>
                                                        <img id="edit_previewImage" src="" alt="Preview" class="w-100 h-100" style="object-fit: cover;">
                                                    </div>
                                                    <div class="custom-file position-absolute w-100 h-100" style="top: 0; left: 0; opacity: 0; cursor: pointer;">
                                                        <input type="file" class="custom-file-input h-100" id="edit_fotoInput" name="foto_profil" style="cursor: pointer;" onchange="document.getElementById('edit_previewImage').src = window.URL.createObjectURL(this.files[0]); document.getElementById('edit_previewImage').classList.remove('d-none'); document.getElementById('edit_cameraIcon').classList.add('d-none');">
                                                    </div>
                                                    <div class="small mt-2 text-primary font-weight-bold" style="cursor: pointer;">Change Photo</div>
                                                </div>

                                                <!-- Key Identity Fields -->
                                                <div class="w-100 text-left">
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">NPK <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control px-3" name="npk" id="edit_npk" readonly>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Nama Lengkap <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control px-3" name="nama" id="edit_nama" required>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Jenis Kelamin</label>
                                                        <select class="form-control px-3" name="jk" id="edit_jk" required style="height: 38px;">
                                                            <option value="" disabled>Pilih Jenis Kelamin</option>
                                                            <option value="L">Laki-laki</option>
                                                            <option value="P">Perempuan</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Status Menikah</label>
                                                        <select class="form-control px-3" name="status" id="edit_status" style="height: 38px;">
                                                            <option value="" disabled>Pilih Status Menikah</option>
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
                                                            <input type="text" class="form-control px-3" name="nik" id="edit_nik">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Nomor KK</label>
                                                            <input type="text" class="form-control px-3" name="nkk" id="edit_nkk">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Nama Ibu</label>
                                                            <input type="text" class="form-control px-3" name="ibu" id="edit_ibu">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Tempat Lahir</label>
                                                            <input type="text" class="form-control px-3" name="tempat_lahir" id="edit_tempat_lahir">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Tanggal Lahir</label>
                                                            <input type="date" class="form-control px-3" name="tgl_lahir" id="edit_tgl_lahir">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Agama</label>
                                                            <select class="form-control px-3" name="agama" id="edit_agama" style="height: 38px;">
                                                                <option value="" disabled>Select</option>
                                                                <option value="ISLAM">Islam</option>
                                                                <option value="KRISTEN">Kristen</option>
                                                                <option value="KATOLIK">Katolik</option>
                                                                <option value="HINDU">Hindu</option>
                                                                <option value="BUDDHA">Buddha</option>
                                                                <option value="LAINNYA">Others</option>
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
                                                            <input type="text" class="form-control px-3" name="hp" id="edit_hp">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Kabupaten</label>
                                                            <input type="text" class="form-control px-3" name="kabupaten" id="edit_kabupaten">
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Alamat</label>
                                                        <textarea class="form-control rounded-lg px-3 py-2" name="alamat" id="edit_alamat" rows="2" style="border-radius: 5px;"></textarea>
                                                    </div>
                                                </div>

                                                <!-- Employment & Education -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="font-weight-bold text-success mb-3 pl-2 border-left-success ml-1">&nbsp;Pendidikan & Pekerjaan</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Department <span class="text-danger">*</span></label>
                                                            <select class="form-control px-3" name="id_dept" id="edit_id_dept" required>
                                                                @foreach($departments as $department)
                                                                    <option value="{{ $department->ID_DEPT }}">{{ $department->DEPARTEMENT }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                             <label class="small font-weight-bold text-muted ml-2">TMK <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control px-3" name="tmk" id="edit_tmk" required>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Jenjang Pendidikan</label>
                                                            <select class="form-control px-3" name="pendidikan" id="edit_pendidikan" style="height: 38px;">
                                                                <option value="" disabled>Select</option>
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
                                                            <input type="text" class="form-control px-3" name="jurusan" id="edit_jurusan">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Jumlah Tanggungan</label>
                                                            <input type="number" class="form-control px-3" name="tanggungan" id="edit_tanggungan">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pb-4 pr-5 bg-white">
                                    <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-warning px-5 font-weight-bold shadow-sm">Update Karyawan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

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
                                                        <input type="text" class="form-control px-3" name="npk" id="npk" required placeholder="Ex: C-00001">
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

                {{-- modal exit --}}
                <!-- Exit Modal -->
                <div class="modal fade" id="exitModal" tabindex="-1" role="dialog" aria-labelledby="exitModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 90%;">
                        <div class="modal-content shadow-lg" style="border-radius: 10px; overflow: hidden;">
                            <div class="modal-header pb-0 pt-4 px-4 bg-white border-0">
                                <h5 class="modal-title font-weight-bold ml-2 text-danger" id="exitModalLabel">Proses Exit Karyawan</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            
                            <form id="exitForm" method="POST">
                                @csrf
                                <div class="modal-body p-4">
                                    <div class="row">
                                        <!-- Left Side: Profile & Identity (Sticky) -->
                                        <div class="col-lg-2 border-right">
                                            <div class="d-flex flex-column align-items-center text-center p-3">
                                                <!-- Photo Display -->
                                                <div class="position-relative mb-4">
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center shadow-sm" style="width: 140px; height: 140px; border: 4px solid white; overflow: hidden;">
                                                        <img id="exit_previewImage" src="" alt="Profile" class="w-100 h-100" style="object-fit: cover;">
                                                    </div>
                                                </div>

                                                <!-- Key Identity Fields -->
                                                <div class="w-100 text-left">
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">NPK</label>
                                                        <input type="text" class="form-control px-3" id="exit_npk" readonly>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Full Name</label>
                                                        <input type="text" class="form-control px-3" id="exit_nama" readonly>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Gender</label>
                                                        <input type="text" class="form-control px-3" id="exit_jk" readonly>
                                                    </div>
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold text-muted ml-2">Marital Status</label>
                                                        <input type="text" class="form-control px-3" id="exit_status" readonly>
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
                                                            <input type="text" class="form-control px-3" id="exit_nik" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Family Card (KK)</label>
                                                            <input type="text" class="form-control px-3" id="exit_nkk" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Mother's Name</label>
                                                            <input type="text" class="form-control px-3" id="exit_ibu" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Place of Birth</label>
                                                            <input type="text" class="form-control px-3" id="exit_tempat_lahir" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Date of Birth</label>
                                                            <input type="text" class="form-control px-3" id="exit_tgl_lahir" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Religion</label>
                                                            <input type="text" class="form-control px-3" id="exit_agama" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Contact -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="font-weight-bold text-info mb-3 pl-2 border-left-info ml-1">&nbsp;Contact & Address</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Phone</label>
                                                            <input type="text" class="form-control px-3" id="exit_hp" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">City</label>
                                                            <input type="text" class="form-control px-3" id="exit_kabupaten" readonly>
                                                        </div>
                                                    </div>
                                                    <div class="form-group mb-3">
                                                        <label class="small font-weight-bold text-muted ml-2">Address</label>
                                                        <textarea class="form-control rounded-lg px-3 py-2" id="exit_alamat" rows="2" style="border-radius: 5px;" readonly></textarea>
                                                    </div>
                                                    <div class="row">
                                                         <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Age</label>
                                                            <input type="text" class="form-control px-3" id="exit_umur" readonly>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Employment & Education -->
                                                <div class="col-md-6 mb-3">
                                                    <h6 class="font-weight-bold text-success mb-3 pl-2 border-left-success ml-1">&nbsp;Employment & Education</h6>
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Dept</label>
                                                            <input type="text" class="form-control px-3" id="exit_dept" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                             <label class="small font-weight-bold text-muted ml-2">TMK</label>
                                                            <input type="text" class="form-control px-3" id="exit_tmk" readonly>
                                                        </div>
                                                        <!-- TKK Field Added Here -->
                                                        <div class="col-md-12 mb-3">
                                                            <label class="small font-weight-bold text-danger ml-2">Tanggal Keluar (TKK) <span class="text-danger">*</span></label>
                                                            <input type="date" class="form-control px-3 border-danger text-danger font-weight-bold" name="tkk" id="exit_tkk">
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Education</label>
                                                            <input type="text" class="form-control px-3" id="exit_pendidikan" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Major</label>
                                                            <input type="text" class="form-control px-3" id="exit_jurusan" readonly>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <label class="small font-weight-bold text-muted ml-2">Dependents</label>
                                                            <input type="text" class="form-control px-3" id="exit_tanggungan" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 pb-4 pr-5 bg-white">
                                    <button type="button" class="btn btn-secondary px-4 font-weight-bold" data-dismiss="modal">Batal</button>
                                    <button type="submit" id="confirmExitBtn" class="btn btn-danger px-5 font-weight-bold shadow-sm">Proses Exit</button>
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
{{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        $('body').on('click', '.btn-show', function() {
            var npk = $(this).data('npk');
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
            $.ajax({
                url: '/biodata/fetch-last-npk',
                type: 'GET',
                success: function(response) {
                    $('#npk').val(response);
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data npk');
                }
            });
        });
        // Handle Exit Button Click
        $('body').on('click', '.btn-exit', function(e) {
            e.preventDefault();
            var npk = $(this).data('id');
            var nama = $(this).data('nama');
            
            // Clear previous TKK
            $('#exit_tkk').val('');
            
            // Store NPK for submission
            $('#confirmExitBtn').data('id', npk);

            // Fetch data to populate the modal
            $.ajax({
                url: '/biodata/show/' + npk,
                type: 'GET',
                success: function(response) {
                    $('#exit_npk').val(response.NPK);
                    $('#exit_nama').val(response.NAMA);
                    $('#exit_jk').val(response.JK == 'L' ? 'LAKI LAKI' : 'PEREMPUAN');
                    $('#exit_status').val(response.STATUS); // Make sure this mapping is correct per your data
                    
                    $('#exit_nik').val(response.KTP);
                    $('#exit_nkk').val(response.NO_KK);
                    $('#exit_ibu').val(response.IBU);
                    $('#exit_tempat_lahir').val(response.TMPTLAHIR);
                    $('#exit_tgl_lahir').val(response.TGLLAHIR);
                    $('#exit_agama').val(response.AGAMA);
                    
                    $('#exit_hp').val(response.HP);
                    $('#exit_kabupaten').val(response.KABUPATEN);
                    $('#exit_alamat').val(response.ALAMAT);
                    $('#exit_umur').val(response.USIA);

                    $('#exit_dept').val(response.BAGIAN);
                    $('#exit_tmk').val(response.TMK);
                    
                    $('#exit_pendidikan').val(response.PDDK);
                    $('#exit_jurusan').val(response.JURUSAN);
                    $('#exit_tanggungan').val(response.TANGGUNGAN);
                    
                    const imageUrl = `/storage/img/profile/${response.NAMA}.jpg`;
                    $('#exit_previewImage').attr('src', imageUrl);

                    $('#exitModal').modal('show');
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data karyawan for exit');
                }
            });
        });

        // Handle Confirm Exit (Submit Form)
        $('#exitForm').on('submit', function(e) {
            e.preventDefault();
            var npk = $('#confirmExitBtn').data('id');
            var tkk = $('#exit_tkk').val();
            var btn = $('#confirmExitBtn');
            
            if(!tkk) {
                Swal.fire('Error', 'Harap isi Tanggal Keluar Kerja (TKK)', 'warning');
                return;
            }

            btn.prop('disabled', true).text('Processing...');

            $.ajax({
                url: '/biodata/exit/' + npk,
                type: 'GET',
                data: { tkk: tkk },
                success: function(response) {
                    $('#exitModal').modal('hide');
                    if(response.original && response.original.status === 'success' || response.status === 'success') {
                         Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data karyawan berhasil di-exit.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                         Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Gagal memproses data.',
                        });
                    }
                },
                error: function(xhr) {
                    $('#exitModal').modal('hide');
                    console.log(xhr.responseText);
                    var msg = 'Terjadi kesalahan saat menghubungi server.';
                    if(xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: msg,
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).text('Proses Exit');
                }
            });
        });
        // Handle Edit Button Click
        $('body').on('click', '.btn-edit', function() {
            var npk = $(this).data('npk');
            
            // Set form action
            $('#editForm').attr('action', '/biodata/update/' + npk);

            $.ajax({
                url: '/biodata/show/' + npk,
                type: 'GET',
                success: function(response) {
                    $('#edit_npk').val(response.NPK);
                    $('#edit_nama').val(response.NAMA);
                    $('#edit_nik').val(response.KTP);
                    $('#edit_nkk').val(response.NO_KK);
                    $('#edit_ibu').val(response.IBU);
                    $('#edit_jk').val(response.JK);
                    $('#edit_tempat_lahir').val(response.TMPTLAHIR);
                    $('#edit_tgl_lahir').val(response.TGLLAHIR);
                    $('#edit_agama').val(response.AGAMA);
                    $('#edit_status').val(response.STATUS); // Ensure values match options
                    $('#edit_hp').val(response.HP);
                    $('#edit_alamat').val(response.ALAMAT);
                    $('#edit_kabupaten').val(response.KABUPATEN);
                    $('#edit_pendidikan').val(response.PDDK);
                    $('#edit_sekolah').val(response.NAMA_SEKOLAH ?? '');
                    $('#edit_jurusan').val(response.JURUSAN);
                    // For dept, we need to select by text or ID. The show response gives BAGIAN (text) or ID?
                    // Controller show returns queries PKWT which has BAGIAN (text). 
                    // But the select inputs use ID_DEPT values.
                    // We need to map BAGIAN text to ID_DEPT value, OR just set it if we can.
                    // However, standard is to store ID in backend and show text.
                    // If PKWT stores text 'FINANCE', and select has <option value='1'>FINANCE</option>, we have a problem matching it.
                    // Let's look at the data. 
                    // Store method uses $request->id_dept to get DEPARTEMENT name and stores that name in PKWT.BAGIAN.
                    // Start method uses $request->id_dept to store ID_DEPT in BIODATA.ID_DEPT.
                    // We should rely on BIODATA table for the ID if possible, but the show method only returns PKWT data currently.
                    // Let's assume we might need to fetch the ID or just try to match text.
                    // Best fix: Update show method to return BIODATA.ID_DEPT as well. But I cannot change that easily right now without checking if I break other things.
                    // Wait, index method joins BIODATA and DEPT.
                    // Show method: $biodata = DB::connection('cii')->table('PKWT')->select('*')->where('NPK', $NPK)->first();
                    // PKWT doesn't seem to have ID_DEPT column based on store method (it stores BAGIAN).
                    // BUT, we can likely find the option by text.
                    
                    $("#edit_id_dept option").filter(function() {
                        return $(this).text() === response.BAGIAN;
                    }).prop('selected', true);

                    $('#edit_tmk').val(response.TMK);
                    $('#edit_tanggungan').val(response.TANGGUNGAN);
                    
                    const imageUrl = `/storage/img/profile/${response.NAMA}.jpg`;
                    $('#edit_previewImage').attr('src', imageUrl);

                    $('#editModal').modal('show');
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data karyawan');
                }
            });
        });

    });
</script>
</html>