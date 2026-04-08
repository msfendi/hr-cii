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
                    <h1 class="h3 mb-0 text-gray-800">Recruitment List</h1>
                    <div>
                        <div class="btn-group" role="group" aria-label="Recruitment Filters">
                            <a href="{{ route('recruitment.index') }}" class="btn btn-outline-primary btn-sm {{ is_null($status) ? 'active' : '' }}">All</a>
                            <a href="{{ route('recruitment.index', ['status' => 'never_confirm']) }}" class="btn btn-outline-secondary btn-sm {{ $status === 'never_confirm' ? 'active' : '' }}">Never Confirm</a>
                            <a href="{{ route('recruitment.index', ['status' => 'ready_test']) }}" class="btn btn-outline-info btn-sm {{ $status === 'ready_test' ? 'active' : '' }}">Ready Test</a>
                            <a href="{{ route('recruitment.index', ['status' => 'ready_interview']) }}" class="btn btn-outline-warning btn-sm {{ $status === 'ready_interview' ? 'active' : '' }}">Ready Interview</a>
                            <a href="{{ route('recruitment.index', ['status' => 'decline']) }}" class="btn btn-outline-danger btn-sm {{ $status === 'decline' ? 'active' : '' }}">Decline</a>
                            <a href="{{ route('recruitment.index', ['status' => 'joining']) }}" class="btn btn-outline-success btn-sm {{ $status === 'joining' ? 'active' : '' }}">Joining</a>
                        </div>
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recruitment Data</h6>
                    </div>
                    <div class="card-body">
                        @if ($message = Session::get('success'))
                        <div class="alert alert-success alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('error'))
                        <div class="alert alert-danger alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('warning'))
                        <div class="alert alert-warning alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('info'))
                        <div class="alert alert-info alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>NIK</th>
                                        <th>Nomor HP</th>
                                        <th>Status Kontrak</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recruitments as $recruitment)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $recruitment->NAMA }}</td>
                                        <td>{{ $recruitment->NPK }}</td>
                                        <td>{{ $recruitment->HP }}</td>
                                        <td>{{ $recruitment->IS_KONTRAK }}</td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm btn-whatsapp" 
                                                    data-nama="{{ $recruitment->NAMA }}" 
                                                    data-phone="{{ $recruitment->HP }}" 
                                                    data-npk="{{ $recruitment->NPK }}" 
                                                    data-toggle="modal" data-target="#whatsappModal">
                                                <i class="fab fa-whatsapp"></i> WA
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

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

        <!-- WhatsApp Modal -->
        <div class="modal fade" id="whatsappModal" tabindex="-1" role="dialog" aria-labelledby="whatsappModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <form action="{{ route('recruitment.sendWhatsApp') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="whatsappModalLabel">Send WhatsApp Confirmation</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Recipient Name</label>
                                        <input type="text" name="nama" id="wa_nama" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="text" name="nomor_hp" id="wa_phone" class="form-control" readonly>
                                        <input type="hidden" name="npk" id="wa_npk">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Message Type</label>
                                <select name="type" id="wa_type" class="form-control" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="invitation">Invitation Test</option>
                                    <option value="interview">Called to Interview HR</option>
                                    <option value="final">Final Result</option>
                                    <option value="rejection">Rejection</option>
                                </select>
                            </div>

                            <div id="wa_datetime_container" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date</label>
                                            <input type="date" id="wa_date" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Time</label>
                                            <input type="time" id="wa_time" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Message Preview</label>
                                <textarea name="message" id="wa_message" class="form-control" rows="5" required></textarea>
                                <small class="text-muted">You can manually edit the message before sending.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Send WhatsApp <i class="fab fa-whatsapp"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

@include('layout.footer')
<script>
    $(document).ready(function() {
        const templates = {
            invitation: "Haloo, [NAMA] \n Selamat! Anda terpilih untuk melanjutkan ke tahap berikutnya dalam Rekrutmen untuk Posisi IT Programmer PT Chutex International Indonesia. \n\n Sebagai langkah selanjutnya, kami mengundang Anda untuk interview pada: \n\n Hari, Tanggal: [DATE] \n Waktu: [TIME] WIB-Selesai \n Alamat: https://maps.app.goo.gl/MfkgQPUbuFhtRHf96 \n Harap konfirmasi kehadiran dengan membalas pesan ini menggunakan format: \n Nama_HADIR/TIDAK HADIR \n\n Diharap datang ke lokasi 30 menit sebelum jadwal interview dengan ketentuan: \n 1. Membawa pulpen hitam \n 2. Membawa KTP asli \n 3. Menggunakan pakaian hitam putih \n\n Bersiaplah, langkah Anda untuk bergabung dengan PT Chutex International Indonesia semakin dekat. Semoga berhasil! \n\n WASPADA PENIPUAN! Dalam proses rekrutmen, PT Chutex International Indonesia TIDAK PERNAH memungut biaya apapun dan TIDAK bekerja sama dengan agen perjalanan manapun. \n\n Salam, \n Recruitment \n PT Chutex International Indonesia",
            interview: "Semangat Pagi, [NAMA]\n Selamat! Anda terpilih untuk melanjutkan ke tahap berikutnya dalam Rekrutmen untuk Posisi IT Programmer PT Chutex International Indonesia.\n\n Sebagai langkah selanjutnya, kami mengundang Anda untuk interview HRD pada:\n\n Hari, Tanggal: [DATE]\n Waktu: [TIME] WIB-Selesai\n Alamat: https://maps.app.goo.gl/MfkgQPUbuFhtRHf96\n\n Harap konfirmasi kehadiran dengan membalas pesan ini menggunakan format:\n Nama_HADIR/TIDAK HADIR\n\n Diharap datang ke lokasi 30 menit sebelum jadwal interview harap membawa:\n * surat lamaran kerja\n * daftar riwayat hidup\n * fc ktp 8 lembar\n * fc kk 3 lembar\n * fc ijazah 5 lembar\n * fc akta kelahiran 1 lembar\n * fc skck yg tglnya masih berlaku 1 lembar\n * surat izin orang tua/suami\n * surat sehat dari dokter\n * pas foto ukuran 3x4 background merah 7 lembar\n\n Bersiaplah, langkah Anda untuk bergabung dengan PT Chutex International Indonesia semakin dekat. Semoga berhasil!\n\n WASPADA PENIPUAN! Dalam proses rekrutmen, PT Chutex International Indonesia TIDAK PERNAH memungut biaya apapun dan TIDAK bekerja sama dengan agen perjalanan manapun.\n\n Salam,\n Recruitment \n PT Chutex International Indonesia",
            final: "Semangat Pagi, [NAMA] \n\n Selamat! Setelah mengikuti rangkaian proses rekrutmen, Saudara dinyatakan LOLOS. \n Efektif per [DATE] Anda resmi bergabung menjadi karyawan di PT Chutex International Indonesia sebagai IT Staff (Programmer). Diharapkan untuk hadir, sebelum pukul 08.00 ke kantor (Alamat: https://maps.app.goo.gl/MfkgQPUbuFhtRHf96) dan bertemu Mbak Lala HRD. \n\n Harap datang dengan pakaian hitam putih dan membawa berkas yang belum lengkap.\n\n WASPADA PENIPUAN! Dalam proses rekrutmen, PT Chutex International Indonesia TIDAK PERNAH memungut biaya apapun dan TIDAK bekerja sama dengan agen perjalanan manapun.\n\n Salam,\n Recruitment \n PT Chutex International Indonesia\n",
            rejection: "Halo [NAMA], terima kasih telah melamar. Saat ini kami belum dapat melanjutkan proses Anda. Tetap semangat!"
        };

        $('.btn-whatsapp').on('click', function() {
            const nama = $(this).data('nama');
            const phone = $(this).data('phone');
            const npk = $(this).data('npk');

            $('#wa_nama').val(nama);
            $('#wa_phone').val(phone);
            $('#wa_npk').val(npk);
            $('#wa_type').val('');
            $('#wa_message').val('');
            $('#wa_datetime_container').hide();
        });

        $('#wa_type, #wa_date, #wa_time').on('change', function() {
            const type = $('#wa_type').val();
            const nama = $('#wa_nama').val();
            const date = $('#wa_date').val();
            const time = $('#wa_time').val();

            if (type === 'invitation' || type === 'interview') {
                $('#wa_datetime_container').show();
            } else {
                $('#wa_datetime_container').hide();
            }

            if (type && templates[type]) {
                let message = templates[type];
                message = message.replace('[NAMA]', nama);
                message = message.replace('[DATE]', date || '____');
                message = message.replace('[TIME]', time || '____');
                
                if (type === 'final') {
                    message = message.replace('[DETAIL]', 'Selamat, Anda dinyatakan lolos');
                }

                $('#wa_message').val(message);
            }
        });
    });
</script>
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
</html>