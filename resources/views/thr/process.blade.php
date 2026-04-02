<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            {{-- ================= PAGE TITLE ================= --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">THR Process</h1>
            </div>
            {{-- ================= CARD ================= --}}
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">THR Process</h6>
              </div>
              <div class="card-body">
                <form method="POST" action="{{ route('thr-process.process') }}" id="thrForm"> @csrf {{-- ================= PERIOD ================= --}}
                  <div class="form-group">
                    <label>THR Period :</label>
                    <select class="form-control" id="period_id" name="period_id" required>
                      <option value="">Pilih Periode</option> @foreach($periods as $period) <option value="{{ $period->id }}">
                        {{ $period->name }}
                      </option> @endforeach
                    </select>
                  </div>
                  <button type="submit" class="btn btn-primary"> Generate THR </button>
                </form>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
    {{-- ================= JS ================= --}}
    <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
    <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
    <script>
      $("#period_id").select2({
        allowClear: true,
        placeholder: 'Pilih Periode THR'
      });
    </script>
    <script>
      /*
========================================
LOADING PROCESS THR
========================================
*/
      $('#thrForm').on('submit', function() {
        Swal.fire({
          title: 'Processing THR',
          text: 'Mohon tunggu THR sedang diproses...',
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: () => {
            Swal.showLoading()
          }
        });
      });
    </script>
  </body>
</html>