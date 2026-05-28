<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3">Health Test</h1>
              <a href="{{ route('health-test.create') }}" class="btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-plus"></i> Create Data </a>
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
                    <tbody> @foreach($data as $row) <tr>
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
                          <button class="btn btn-info btn-circle btn-sm" data-toggle="modal" data-target="#detailModal{{ $row->id }}">
                            <i class="fas fa-eye"></i>
                          </button>
                          <a href="{{ route('health-test.pdf',$row->id) }}" class="btn btn-danger btn-circle btn-sm">
                            <i class="fa fa-file-pdf"></i>
                          </a>
                          <a href="{{ route('health-test.edit',$row->id) }}" class="btn btn-warning btn-circle btn-sm">
                            <i class="fas fa-edit"></i>
                          </a>
                          <a href="{{ route('health-test.delete',$row->id) }}" onclick="return confirm('Delete data?')" class="btn btn-danger btn-circle btn-sm">
                            <i class="fas fa-trash"></i>
                          </a>
                        </td>
                      </tr>
                      {{-- ================= MODAL DETAIL ================= --}}
                      <div class="modal fade" id="detailModal{{ $row->id }}">
                        <div class="modal-dialog modal-xl">
                          <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                              <h5 class="modal-title"> Detail Health Test — {{ $row->nama }} ({{ $row->nik }}) </h5>
                              <button class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                              <div class="row">
                                {{-- ================= KONDISI FISIK ================= --}}
                                <div class="col-lg-8">
                                  <div class="card shadow-sm h-100">
                                    <div class="card-header bg-primary text-white"> Kondisi Fisik </div>
                                    <div class="card-body">
                                      <div class="row">
                                        <div class="col-md-3">
                                          <b>Cacat</b>
                                          <div class="border rounded p-2 text-center"> {!! $row->cacat ? '✅ YA' : '❌ TIDAK' !!} </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Buta Warna</b>
                                          <div class="border rounded p-2 text-center"> {!! $row->buta_warna ? '✅ YA' : '❌ TIDAK' !!} </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Visus Mata OD</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->visus_mata_od }}
                                          </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                          <b>Visus Mata OS</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->visus_mata_os }}
                                          </div>
                                        </div>
                                      </div>
                                      <hr>
                                      <div class="row">
                                        <div class="col-md-3">
                                          <b>Tinggi</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->tinggi }} cm
                                          </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Berat</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->berat }} kg
                                          </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                          <b>Suhu</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->suhu }}
                                          </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Gigi</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->gigi }}
                                          </div>
                                        </div>
                                      </div>
                                      <hr>
                                      <div class="row">
                                        <div class="col-md-3">
                                          <b>Tekanan Darah</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->tekanan_darah }}
                                          </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Respirasi</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->respirasi }}
                                          </div>
                                        </div>
                                        <div class="col-md-3">
                                          <b>Denyut</b>
                                          <div class="border rounded p-2 text-center">
                                            {{ $row->denyut }}
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                {{-- ================= RIWAYAT ================= --}}
                                <div class="col-lg-4">
                                  <div class="card shadow-sm h-100">
                                    <div class="card-header bg-warning"> Riwayat Penyakit </div>
                                    <div class="card-body text-center"> @php function check($v){ return $v ? '✅ YA':'❌ TIDAK'; } @endphp <div class="row">
                                        <div class="col-6 mb-3">
                                          <b>Paru</b>
                                          <div>{{ check($row->paru) }}</div>
                                        </div>
                                        <div class="col-6 mb-3">
                                          <b>Hepatitis</b>
                                          <div>{{ check($row->hepatitis) }}</div>
                                        </div>
                                        <div class="col-6 mb-3">
                                          <b>Jantung</b>
                                          <div>{{ check($row->jantung) }}</div>
                                        </div>
                                        <div class="col-6 mb-3">
                                          <b>Thypoid</b>
                                          <div>{{ check($row->thypoid) }}</div>
                                        </div>
                                        <div class="col-6 mb-3">
                                          <b>Alergi</b>
                                          <div>{{ check($row->alergi) }}</div>
                                        </div>
                                        <div class="col-6 mb-3">
                                          <b>Ashma</b>
                                          <div>{{ check($row->ashma) }}</div>
                                        </div>
                                      </div> @if($row->lain)
                                      <hr>
                                      <b>Lainnya :</b>
                                      <div class="border rounded p-2 mt-2">
                                        {{ $row->lain }}
                                      </div> @endif
                                    </div>
                                  </div>
                                </div>
                              </div>
                              {{-- ================= KESIMPULAN ================= --}}
                              <div class="card shadow-sm mt-3">
                                <div class="card-header bg-success text-white"> Kesimpulan </div>
                                <div class="card-body">
                                  <div class="alert alert-{{ $row->kesimpulan ? 'success':'danger' }}">
                                    <h5 class="mb-0">
                                      {{ $row->kesimpulan ? 'SEHAT' : 'KURANG SEHAT' }}
                                    </h5>
                                  </div> @if($row->remark) <b>Remark :</b>
                                  <div class="border rounded p-2">
                                    {{ $row->remark }}
                                  </div> @endif
                                </div>
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                          </div>
                        </div>
                      </div> @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
    {{-- DATATABLE --}}
    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script>
      $(document).ready(function() {
        $('#dataTable').DataTable({
          pageLength: 25,
          responsive: true,
          autoWidth: false
        });
      });
    </script>
  </body>
</html>