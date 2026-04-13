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
               <h1 class="h3 mb-0 text-gray-800">Payroll Approval</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">Data Approval Payroll</h6>
               </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Payroll Run</th>
                              <th>Payroll Period</th>
                              <th>Export File</th>
                              <th>Export Status</th>
                              <th>Progress</th>
                              <th>Approval Status</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->payroll_run_id }}</td>
                              <td>{{ $row->period_name }}</td>
                              
                                <td class="text-center">
                                    {{-- DOWNLOAD EXCEL --}}
                                    @if($row->is_exported && $row->file_excel)
                                        <a class="btn btn-success btn-sm"
                                        href="{{ asset('storage/'.$row->file_excel) }}"
                                        target="_blank">
                                            <i class="fas fa-file-excel mr-1"></i> Excel
                                        </a>
                                    @endif

                                    {{-- DOWNLOAD PDF --}}
                                    @if($row->is_exported && $row->file_pdf)
                                        <a class="btn btn-danger btn-sm"
                                            href="{{ asset('storage/'.$row->file_pdf) }}"
                                        target="_blank">
                                            <i class="fas fa-file-pdf mr-1"></i> PDF
                                        </a>
                                    @endif

                                    {{-- DOWNLOAD PDF PENGELUARAN --}}
                                    @if($row->is_exported && $row->file_peng)
                                        <a class="btn btn-warning btn-sm"
                                            href="{{ asset('storage/'.$row->file_peng) }}"
                                        target="_blank">
                                            <i class="fas fa-file-pdf mr-1"></i> Pengeluaran
                                        </a>
                                    @endif

                                </td>
                              <td>
                                @if($row->is_exported)
                                    <span class="badge badge-success">Sudah Export</span>
                                @else
                                    <span class="badge badge-secondary">Belum Export</span>
                                @endif
                              </td>
                              {{-- =========================
                              PROGRESS UI
                              ========================= --}}
                              <td>
                                @foreach($row->progress as $levelIndex => $p)
                                @php
                                $users = $p['users'];
                                if ($p['status'] === 'approve') {
                                $statusList = array_fill(0, count($users), 'approve');
                                } else {
                                $decodedStatus = json_decode($p['status'], true);
                                $statusList = is_array($decodedStatus)
                                ? $decodedStatus
                                : array_fill(0, count($users), 'waiting');
                                }
                                @endphp
                                <div class="mb-2 p-2 border rounded bg-light">
                                    @foreach($users as $idx => $user)
                                    @php
                                    $beforeApproved = true;
                                    for ($i = 0; $i < $idx; $i++) {
                                    if ($statusList[$i] !== 'approve') {
                                    $beforeApproved = false;
                                    }
                                    }
                                    @endphp
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>
                                        <b>{{ $user['npk'] }}</b> - {{ $user['name'] }}
                                        </span>
                                        @if($statusList[$idx] == 'approve')
                                        <span class="badge badge-success">✔ Approved</span>
                                        @elseif(!$beforeApproved)
                                        <span class="badge badge-secondary">Waiting Previous</span>
                                        @else
                                        <span class="badge badge-warning">Waiting</span>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                                @endforeach
                                </td>
                              <td>
                                 @if($row->status == 'finish')
                                 <span class="badge badge-success">Finish</span>
                                 @else
                                 <span class="badge badge-warning">Pending</span>
                                 @endif
                              </td>
                              {{-- =========================
                              ACTION BUTTON (SEQUENTIAL)
                              ========================= --}}
                              <td class="text-center">
                                @php
                                    $progress = collect($row->progress);

                                    $currentIndex = $progress->search(function ($item) {
                                        return $item['status'] !== 'approve';
                                    });

                                    $canApprove = false;

                                    if ($currentIndex !== false) {

                                        $current = $progress[$currentIndex];

                                        $npkList = is_array($current['npk']) 
                                            ? $current['npk'] 
                                            : json_decode($current['npk'], true);

                                        if (!is_array($npkList)) $npkList = [];

                                        if ($current['status'] === 'pending') {
                                            $statusList = array_fill(0, count($npkList), 'waiting');
                                        } else {
                                            $decodedStatus = json_decode($current['status'], true);
                                            $statusList = is_array($decodedStatus)
                                                ? $decodedStatus
                                                : array_fill(0, count($npkList), 'waiting');
                                        }

                                        foreach ($npkList as $idx => $npk) {

                                            $beforeApproved = true;

                                            for ($i = 0; $i < $idx; $i++) {
                                                if ($statusList[$i] !== 'approve') {
                                                    $beforeApproved = false;
                                                }
                                            }

                                            if (
                                                $npk == auth()->user()->npk &&
                                                $statusList[$idx] != 'approve' &&
                                                $beforeApproved
                                            ) {
                                                $canApprove = true;
                                            }
                                        }
                                    }
                                @endphp

                                {{-- =========================
                                🔥 RULE EXPORT DULU
                                ========================= --}}
                                @if(!$row->is_exported)
                                    <span class="badge badge-secondary">Waiting for Export</span>

                                @else

                                    {{-- NORMAL FLOW --}}
                                    @if($canApprove)
                                        <button 
                                            class="btn btn-success btn-sm btn-approve"
                                            data-id="{{ $row->id }}">
                                            <i class="fas fa-check"></i> Approve
                                        </button>

                                    @elseif($row->status == 'finish')
                                        <span class="badge badge-success">Done</span>

                                    @else
                                        <span class="badge badge-secondary">Waiting</span>
                                    @endif

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
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
        $(document).ready(function(){

            $('#dataTable').DataTable({
                order: [[0,'desc']], // pakai urutan ID dari Laravel
                pageLength: 10,
                responsive: true,
                autoWidth:false
            });

        });
        </script>
      <script>
         $('.btn-approve').click(function() {
             let id = $(this).data('id');
         
             Swal.fire({
                 title: 'Approve?',
                 text: "Anda yakin ingin approve?",
                 icon: 'question',
                 showCancelButton: true,
                 confirmButtonText: 'Yes'
             }).then((result) => {
                 if (result.isConfirmed) {
                     $.ajax({
                         url: '/payroll-approve/' + id + '/approve',
                         type: 'POST',
                         data: {
                             _token: '{{ csrf_token() }}',
                             npk: '{{ auth()->user()->npk }}'
                         },
                         success: function(res) {
                             Swal.fire('Success', res.message, 'success');
                             setTimeout(() => location.reload(), 1000);
                         },
                         error: function(err) {
                             Swal.fire('Error', err.responseJSON.message, 'error');
                         }
                     });
                 }
             });
         });
      </script>
   </body>
</html>