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
                        <h1 class="h3 mb-0 text-gray-800">Manajemen Persetujuan Cuti</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Daftar Pengajuan Cuti (Menunggu Keputusan Anda)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="dataTableApproval" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Tgl Pengajuan</th>
                                            <th>Karyawan</th>
                                            <th>Departemen</th>
                                            <th>Jenis Cuti</th>
                                            <th>Tanggal Cuti</th>
                                            <th>Keterangan</th>
                                            <th>Status Anda</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $key => $row)
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ \Carbon\Carbon::parse($row['created_at'])->format('d M Y') }}</td>
                                            <td>
                                                <strong>{{ $row['nama'] }}</strong><br>
                                                <small class="text-muted">{{ $row['npk'] }}</small>
                                            </td>
                                            <td>{{ $row['dept'] }}</td>
                                            <td>{{ $row['leave_type'] }}</td>
                                            <td>
                                                {{ \Carbon\Carbon::parse($row['start_date'])->format('d M') }} - {{ \Carbon\Carbon::parse($row['end_date'])->format('d M Y') }}
                                                <br>
                                                <span class="badge badge-info">{{ $row['total_days'] }} Hari</span>
                                            </td>
                                            <td>{{ $row['reason'] }}</td>
                                            <td>
                                                @if($row['status'] == 'pending')
                                                    <span class="badge badge-warning">Menunggu</span>
                                                @elseif($row['status'] == 'approved')
                                                    <span class="badge badge-success">Disetujui</span>
                                                @elseif($row['status'] == 'rejected')
                                                    <span class="badge badge-danger">Ditolak</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ ucfirst($row['status']) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($row['status'] == 'pending')
                                                    <button class="btn btn-sm btn-success btn-approve mb-1" data-id="{{ $row['id'] }}" title="Setujui">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-reject mb-1" data-id="{{ $row['id'] }}" title="Tolak">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.container-fluid -->
            </div>
            <!-- End of Main Content -->

            @include('layout.footer')
        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
    
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#dataTableApproval').DataTable({
                "language": {
                    "emptyTable": "Tidak ada permohonan cuti baru."
                }
            });

            // Action: Approve
            $(document).on('click', '.btn-approve', function() {
                var id = $(this).data('id');
                var button = $(this);
                
                Swal.fire({
                    title: 'Setujui Permohonan?',
                    text: 'Anda yakin ingin menyetujui pengajuan cuti ini?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#1cc88a',
                    cancelButtonColor: '#858796',
                    confirmButtonText: 'Ya, Setujui',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                        $.ajax({
                            url: "{{ url('/approval/cuti/approve') }}/" + id,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Sukses', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Gagal', response.message, 'error');
                                    button.prop('disabled', false).html('<i class="fas fa-check"></i>');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                                button.prop('disabled', false).html('<i class="fas fa-check"></i>');
                            }
                        });
                    }
                });
            });

            // Action: Reject
            $(document).on('click', '.btn-reject', function() {
                var id = $(this).data('id');
                var button = $(this);
                
                Swal.fire({
                    title: 'Tolak Permohonan?',
                    text: 'Anda yakin ingin menolak pengajuan cuti ini?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74a3b',
                    cancelButtonColor: '#858796',
                    confirmButtonText: 'Ya, Tolak',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                        $.ajax({
                            url: "{{ url('/approval/cuti/reject') }}/" + id,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Ditolak', response.message, 'success').then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Gagal', response.message, 'error');
                                    button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
                                button.prop('disabled', false).html('<i class="fas fa-times"></i>');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
