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
                  <button id="btnProcess" type="submit" class="btn btn-primary"> Generate THR </button>
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
    $(document).on('click','#btnProcess',function(e){

    e.preventDefault();

    let periodId = $('#period_id').val();

    if(!periodId){
        Swal.fire({
            icon:'warning',
            title:'Periode belum dipilih'
        });
        return;
    }

    /*
    ==========================================
    ROUTES
    ==========================================
    */

    let url = "{{ route('thr-process.process') }}";

    let progressUrlTemplate =
        "{{ route('thr.process.progress', ':period_id') }}";

    let progressUrl =
        progressUrlTemplate.replace(':period_id', periodId);

    /*
    ==========================================
    CONFIRM
    ==========================================
    */

    Swal.fire({
        title: "Generate Thr?",
        text: "The thr process will begin. This may take a few minutes depending on the amount of data. Do you want to proceed?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!"
    }).then((result)=>{

        if(!result.isConfirmed) return;

        Swal.fire({
            title: "Thr is being processed!",
            html: `
                <div class="w-100">

                    <div id="progress-status"
                        style="font-weight:600;margin-bottom:10px">
                        Initializing...
                    </div>

                    <div class="progress" style="height:25px;">
                        <div id="progress-bar"
                            class="progress-bar progress-bar-striped progress-bar-animated"
                            style="width:0%">
                            0%
                        </div>
                    </div>

                </div>
            `,
            allowOutsideClick:false,
            showConfirmButton:false,
            didOpen: ()=>{

                Swal.showLoading();

                /*
                ==========================================
                START PROCESS (FIXED)
                ==========================================
                */

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        period_id: periodId,
                        refresh: 1
                    },
                    error:function(xhr){
                        console.log('Start process error',xhr.responseText);
                    }
                });

                /*
                ==========================================
                POLLING PROGRESS
                ==========================================
                */

                let interval = setInterval(function(){

                    $.ajax({
                        url: progressUrl,
                        type:'GET',
                        success:function(res){

                            let progress = res.progress ?? 0;
                            let status   = res.status ?? 'Processing';

                            $('#progress-bar')
                                .css('width',progress+'%')
                                .text(progress+'%');

                            $('#progress-status')
                                .text(status);

                            if(progress >= 100){

                                clearInterval(interval);

                                Swal.fire({
                                    icon:'success',
                                    title:'Thr Finished',
                                    text:'Thr Successfully Calculated!'
                                }).then(()=>{
                                    window.location.href = "{{ route('thr-process.index') }}";
                                });

                            }
                        },
                        error:function(xhr){
                            console.log('Polling error',xhr.status);
                        }
                    });

                },2000);

            }
        });

    });

});

</script>
  </body>
</html>