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
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#importModal">
                            <i class="fas fa-file-import"></i> Import Excel
                        </button>
                    </div>

                    <!-- Import Modal -->
                    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="importModalLabel">Import Pelamar Data</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form action="{{ route('pelamar.import') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="file">Choose Excel File</label>
                                            <input type="file" class="form-control-file" id="file" name="file" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary">Import</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
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
                                                data-id="{{ $pelamar->ID }}"
                                                data-toggle="modal" 
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
                <div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="assignModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl" role="document">
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
                                        <div class="col-md-6">
                                            <h6 class="font-weight-bold text-primary mb-3">Personal Information</h6>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">NPK</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_npk" name="npk">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Nama</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_nama" name="nama">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">NIK</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_nik" name="nik">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Jenis Kelamin</label>
                                                <div class="col-sm-8">
                                                    <select class="form-control py-0" id="assign_jk" name="jk">
                                                        <option value="L">Laki-laki</option>
                                                        <option value="P">Perempuan</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Tempat Lahir</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_tempat_lahir" name="tempat_lahir">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Tgl Lahir</label>
                                                <div class="col-sm-8">
                                                    <input type="date" class="form-control py-0" id="assign_tgl_lahir" name="tgl_lahir">
                                                </div>
                                            </div>
                                            {{-- <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Umur</label>
                                                <div class="col-sm-8">
                                                    <input type="number" class="form-control py-0" id="assign_umur" name="umur">
                                                </div>
                                            </div> --}}
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Agama</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_agama" name="agama">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Status</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_status" name="status">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <h6 class="font-weight-bold text-primary mb-3">Contact & Education</h6>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">No HP</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_hp" name="hp">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Alamat</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_alamat" name="alamat" placeholder="Alamat Lengkap">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Kabupaten</label>
                                                <div class="col-sm-8">
                                                     <input type="text" class="form-control py-0" id="assign_kabupaten" name="kabupaten" placeholder="Kabupaten">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Pendidikan</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_pendidikan" name="pendidikan">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Sekolah/Univ</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_sekolah" name="sekolah">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">Jurusan</label>
                                                <div class="col-sm-8">
                                                    <input type="text" class="form-control py-0" id="assign_jurusan" name="jurusan">
                                                </div>
                                            </div>
                                            <div class="form-group row mb-1">
                                                <label class="col-sm-4 col-form-label py-0">TB / BB</label>
                                                <div class="col-sm-8 input-group">
                                                    <input type="number" class="form-control py-0" id="assign_tb" name="tb" placeholder="TB">
                                                    <div class="input-group-append"><span class="input-group-text py-0">cm</span></div>
                                                    <input type="number" class="form-control py-0" id="assign_bb" name="bb" placeholder="BB">
                                                    <div class="input-group-append"><span class="input-group-text py-0">kg</span></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>
                                    
                                    <h6 class="font-weight-bold text-success mb-3 px-3">Assign Employee</h6>
                                    <div class="row px-3">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="assign_id_dept">Department <span class="text-danger">*</span></label>
                                                <select class="form-control" id="assign_id_dept" name="id_dept" required>
                                                    <option value="">Select Department</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->ID_DEPT }}">{{ $dept->DEPARTEMENT }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="assign_tmk">Tanggal Masuk Kerja (TMK) <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="assign_tmk" name="tmk" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
{{-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script> --}}
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<script>
    $(document).ready(function() {
        $('.btn-assign').on('click', function() {
            var id = $(this).data('id');
            $('#assign_id_pelamar').val(id);

            // Clear previous data
            $('#assign_npk').val('Loading...');
            $('#assign_nama').val('');
            $('#assign_nik').val('');
            $('#assign_jk').val('');
            $('#assign_tempat_lahir').val('');
            $('#assign_tgl_lahir').val('');
            $('#assign_umur').val('');
            $('#assign_agama').val('');
            $('#assign_status').val('');
            $('#assign_hp').val('');
            $('#assign_alamat').text('');
            $('#assign_kabupaten').text('');
            $('#assign_pendidikan').val('');
            $('#assign_sekolah').val('');
            $('#assign_jurusan').val('');
            $('#assign_tb').text('');
            $('#assign_bb').text('');

            $.ajax({
                url: '/pelamar/detail/' + id,
                type: 'GET',
                success: function(response) {
                    $('#assign_npk').val(response.NPK);
                    $('#assign_nama').val(response.NAMA);
                    $('#assign_nik').val(response.NIK);
                    $('#assign_jk').val(response.JENIS_KELAMIN);
                    $('#assign_tempat_lahir').val(response.TMPT_LAHIR);
                    $('#assign_tgl_lahir').val(response.TGL_LAHIR);
                    $('#assign_umur').val(response.UMUR);
                    $('#assign_agama').val(response.AGAMA);
                    $('#assign_status').val(response.STATUS);
                    $('#assign_hp').val(response.HP);
                    $('#assign_alamat').val(response.ALAMAT_LENGKAP);
                    $('#assign_kabupaten').val(response.KABUPATEN);
                    $('#assign_pendidikan').val(response.PENDIDIKAN);
                    $('#assign_sekolah').val(response.NAMA_SEKOLAH);
                    $('#assign_jurusan').val(response.JURUSAN);
                    $('#assign_tb').val(response.TINGGI_BADAN);
                    $('#assign_bb').val(response.BERAT_BADAN);

                    // Reformat date for date input (YYYY-MM-DD) if necessary
                    // Assuming response.TGL_LAHIR is YYYY-MM-DD or standard format. 
                    // If it is DD-MM-YYYY, might need conversion.
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    alert('Gagal mengambil data pelamar');
                }
            });
        });

        $('.assign-save').on('click', function() {
            $.ajax({
                url: '/pelamar/assign',
                type: 'POST',
                data: {
                    npk: $('#assign_npk').val(),
                    nama: $('#assign_nama').val(),
                    nik: $('#assign_nik').val(),
                    jk: $('#assign_jk').val(),
                    tempat_lahir: $('#assign_tempat_lahir').val(),
                    tgl_lahir: $('#assign_tgl_lahir').val(),
                    // umur: $('#assign_umur').val(),
                    agama: $('#assign_agama').val(),
                    status: $('#assign_status').val(),
                    hp: $('#assign_hp').val(),
                    alamat: $('#assign_alamat').val(),
                    kabupaten: $('#assign_kabupaten').val(),
                    pendidikan: $('#assign_pendidikan').val(),
                    sekolah: $('#assign_sekolah').val(),
                    jurusan: $('#assign_jurusan').val(),
                    tb: $('#assign_tb').val(),
                    bb: $('#assign_bb').val(),
                },
                // success: function(response) {
                //     console.log(response);
                //     $('#assignModal').modal('hide');
                //     Swal.fire({
                //         icon: 'success',
                //         title: 'Success...',
                //         text: 'Data pelamar berhasil disimpan!',
                //     });
                // },
                // error: function(xhr) {
                //     console.log(xhr.responseText);
                //     Swal.fire({
                //         icon: 'error',
                //         title: 'Error...',
                //         text: 'Gagal menambahkan data pelamar!',
                //     });
                // }
            })
        })
    });
</script>
</html>