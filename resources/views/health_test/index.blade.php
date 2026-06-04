<!DOCTYPE html>
<html lang="en">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3">Health Test</h1>

                        <a href="{{ route('health-test.create') }}" class="btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-plus"></i> Create Data
                        </a>
                    </div>

                    <div class="card shadow">
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-sm" id="dataTable">
                                    <thead class="thead-light text-center">
                                        <tr>
                                            <th>ID</th>
                                            <th>NIK</th>
                                            <th>Nama</th>
                                            <th>Kesimpulan</th>
                                            <th>Tanggal Test</th>
                                            <th width="160">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach($data as $row)
                                        <tr>
                                            <td>{{ $row->id }}</td>
                                            <td>{{ $row->nik }}</td>
                                            <td>{{ $row->nama }}</td>

                                            <td class="text-center">
                                                <span class="badge badge-{{ $row->kesimpulan ? 'success':'danger' }}">
                                                    {{ $row->kesimpulan ? 'SEHAT':'KURANG SEHAT' }}
                                                </span>
                                            </td>

                                            <td>
                                                {{ \Carbon\Carbon::parse($row->created_at)->format('d M Y') }}
                                            </td>

                                            <td class="text-center">

                                                {{-- DETAIL --}}
                                                <button
                                                    class="btn btn-info btn-circle btn-sm btn-detail"

                                                    data-id="{{ $row->id }}"
                                                    data-nik="{{ $row->nik }}"
                                                    data-nama="{{ $row->nama }}"
                                                    data-kesimpulan="{{ $row->kesimpulan }}"
                                                    data-created="{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y') }}"

                                                    data-cacat="{{ $row->cacat }}"
                                                    data-buta_warna="{{ $row->buta_warna }}"
                                                    data-visus_mata_od="{{ $row->visus_mata_od }}"
                                                    data-visus_mata_os="{{ $row->visus_mata_os }}"
                                                    data-tinggi="{{ $row->tinggi }}"
                                                    data-berat="{{ $row->berat }}"
                                                    data-suhu="{{ $row->suhu }}"
                                                    data-gigi="{{ $row->gigi }}"
                                                    data-tekanan_darah="{{ $row->tekanan_darah }}"
                                                    data-respirasi="{{ $row->respirasi }}"
                                                    data-denyut="{{ $row->denyut }}"

                                                    data-paru="{{ $row->paru }}"
                                                    data-hepatitis="{{ $row->hepatitis }}"
                                                    data-jantung="{{ $row->jantung }}"
                                                    data-thypoid="{{ $row->thypoid }}"
                                                    data-alergi="{{ $row->alergi }}"
                                                    data-ashma="{{ $row->ashma }}"
                                                    data-lain="{{ $row->lain }}"
                                                    data-remark="{{ $row->remark }}"

                                                    data-toggle="modal"
                                                    data-target="#detailModal">

                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                {{-- PDF --}}
                                                @if($row->file_surat_sehat)

                                                <a href="{{ asset('storage/'.$row->file_surat_sehat) }}"
                                                    target="_blank"
                                                    class="btn btn-danger btn-circle btn-sm"
                                                    title="Lihat PDF">
                                                    <i class="fa fa-file-pdf"></i>
                                                </a>

                                                @else

                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-circle btn-sm"
                                                    disabled
                                                    title="PDF belum tersedia">
                                                    <i class="fa fa-file-pdf"></i>
                                                </button>

                                                @endif

                                                {{-- EDIT --}}
                                                <a href="{{ route('health-test.edit',$row->id) }}"
                                                    class="btn btn-warning btn-circle btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                {{-- DELETE --}}
                                                <a href="javascript:void(0)"
                                                    data-url="{{ route('health-test.delete',$row->id) }}"
                                                    class="btn btn-danger btn-circle btn-sm btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>

                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>

                                </table>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            @include('layout.footer')
        </div>
    </div>

    {{-- ================= MODAL DETAIL ================= --}}
    <div class="modal fade" id="detailModal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">
                        Detail Health Test
                    </h5>

                    <button class="close text-white" data-dismiss="modal">
                        &times;
                    </button>
                </div>

                <div class="modal-body">

                    <div class="row">

                        {{-- ================= KONDISI FISIK ================= --}}
                        <div class="col-lg-8">
                            <div class="card shadow-sm h-100">

                                <div class="card-header bg-primary text-white">
                                    Kondisi Fisik
                                </div>

                                <div class="card-body">

                                    <div class="row">

                                        <div class="col-md-3">
                                            <b>Cacat</b>

                                            <div class="border rounded p-2 text-center" id="cacat">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Buta Warna</b>

                                            <div class="border rounded p-2 text-center" id="buta_warna">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Visus Mata OD</b>

                                            <div class="border rounded p-2 text-center" id="visus_mata_od">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Visus Mata OS</b>

                                            <div class="border rounded p-2 text-center" id="visus_mata_os">
                                            </div>
                                        </div>

                                    </div>

                                    <hr>

                                    <div class="row">

                                        <div class="col-md-3">
                                            <b>Tinggi</b>

                                            <div class="border rounded p-2 text-center" id="tinggi">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Berat</b>

                                            <div class="border rounded p-2 text-center" id="berat">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Suhu</b>

                                            <div class="border rounded p-2 text-center" id="suhu">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Gigi</b>

                                            <div class="border rounded p-2 text-center" id="gigi">
                                            </div>
                                        </div>

                                    </div>

                                    <hr>

                                    <div class="row">

                                        <div class="col-md-3">
                                            <b>Tekanan Darah</b>

                                            <div class="border rounded p-2 text-center" id="tekanan_darah">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Respirasi</b>

                                            <div class="border rounded p-2 text-center" id="respirasi">
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <b>Denyut</b>

                                            <div class="border rounded p-2 text-center" id="denyut">
                                            </div>
                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- ================= RIWAYAT ================= --}}
                        <div class="col-lg-4">
                            <div class="card shadow-sm h-100">

                                <div class="card-header bg-warning">
                                    Riwayat Penyakit
                                </div>

                                <div class="card-body text-center">

                                    <div class="row">

                                        <div class="col-6 mb-3">
                                            <b>Paru</b>
                                            <div id="paru"></div>
                                        </div>

                                        <div class="col-6 mb-3">
                                            <b>Hepatitis</b>
                                            <div id="hepatitis"></div>
                                        </div>

                                        <div class="col-6 mb-3">
                                            <b>Jantung</b>
                                            <div id="jantung"></div>
                                        </div>

                                        <div class="col-6 mb-3">
                                            <b>Thypoid</b>
                                            <div id="thypoid"></div>
                                        </div>

                                        <div class="col-6 mb-3">
                                            <b>Alergi</b>
                                            <div id="alergi"></div>
                                        </div>

                                        <div class="col-6 mb-3">
                                            <b>Ashma</b>
                                            <div id="ashma"></div>
                                        </div>

                                    </div>

                                    <div id="lain_wrapper" style="display:none;">
                                        <hr>

                                        <b>Lainnya :</b>

                                        <div class="border rounded p-2 mt-2" id="lain">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- ================= KESIMPULAN ================= --}}
                    <div class="card shadow-sm mt-3">

                        <div class="card-header bg-success text-white">
                            Kesimpulan
                        </div>

                        <div class="card-body">

                            <div id="kesimpulan_alert"></div>

                            <div id="remark_wrapper" style="display:none;">
                                <b>Remark :</b>

                                <div class="border rounded p-2" id="remark">
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>

</body>

{{-- DATATABLE --}}
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).on('click', '.btn-delete', function() {

        let url = $(this).data('url');

        Swal.fire({
            title: 'Delete Data?',
            text: 'Data yang sudah dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {

            if (result.isConfirmed) {
                window.location.href = url;
            }

        });

    });
</script>

<script>
    $(document).ready(function() {

        $('#dataTable').DataTable({
            pageLength: 25,
            responsive: true,
            autoWidth: false
        });

        function check(v) {
            return v == 1 ? '✅ YA' : '❌ TIDAK';
        }

        $('.btn-detail').click(function() {

            let data = $(this).data();

            $('#modalTitle').html(
                'Detail Health Test — ' +
                data.nama +
                ' (' + data.nik + ')'
            );

            $('#cacat').html(check(data.cacat));
            $('#buta_warna').html(check(data.buta_warna));

            $('#visus_mata_od').html(data.visus_mata_od);
            $('#visus_mata_os').html(data.visus_mata_os);

            $('#tinggi').html(data.tinggi + ' cm');
            $('#berat').html(data.berat + ' kg');

            $('#suhu').html(data.suhu);
            $('#gigi').html(data.gigi);

            $('#tekanan_darah').html(data.tekanan_darah);
            $('#respirasi').html(data.respirasi);
            $('#denyut').html(data.denyut);

            $('#paru').html(check(data.paru));
            $('#hepatitis').html(check(data.hepatitis));
            $('#jantung').html(check(data.jantung));
            $('#thypoid').html(check(data.thypoid));
            $('#alergi').html(check(data.alergi));
            $('#ashma').html(check(data.ashma));

            if (data.lain) {
                $('#lain_wrapper').show();
                $('#lain').html(data.lain);
            } else {
                $('#lain_wrapper').hide();
            }

            let kesimpulanHtml = '';

            if (data.kesimpulan == 1) {

                kesimpulanHtml = `
                    <div class="alert alert-success">
                        <h5 class="mb-0">SEHAT</h5>
                    </div>
                `;

            } else {

                kesimpulanHtml = `
                    <div class="alert alert-danger">
                        <h5 class="mb-0">KURANG SEHAT</h5>
                    </div>
                `;

            }

            $('#kesimpulan_alert').html(kesimpulanHtml);

            if (data.remark) {
                $('#remark_wrapper').show();
                $('#remark').html(data.remark);
            } else {
                $('#remark_wrapper').hide();
            }

        });

    });
</script>

</html>