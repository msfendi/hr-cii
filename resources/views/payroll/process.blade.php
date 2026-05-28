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
               <h1 class="h3 mb-0 text-gray-800">Payroll Process</h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Payroll Process
                  </h6>
               </div>
               <div class="card-body">
                  <form method="POST"
                     action="{{ route('payroll-process.process') }}"
                     id="payrollForm">
                     @csrf
                     <div class="form-group">
                        <label>Payroll Period :</label>
                        <select class="form-control"
                           id="period_id"
                           name="period_id"
                           required>
                           <option value="">Pilih Periode</option>
                           @foreach($periods as $period)
                           <option value="{{ $period->id }}">
                              {{ $period->name }}
                           </option>
                           @endforeach
                        </select>
                     </div>
                     {{-- ================= VALIDATION RESULT ================= --}}
                     <div id="approvalBox"
                        class="mt-4"
                        style="display:none">
                        <div class="card border-left-info shadow">
                           <div class="card-header">
                              <b>Approval Validation</b>
                           </div>
                           <div class="card-body">
                              <div id="approvalLoading"
                                 class="text-center p-3"
                                 style="display:none">
                                 <i class="fas fa-spinner fa-spin fa-2x"></i>
                              </div>
                              <table class="table table-sm table-bordered mb-0">
                                 <thead>
                                    <tr>
                                       <th>Payroll Component</th>
                                       <th>Status</th>
                                       <th>Approved At</th>
                                    </tr>
                                 </thead>
                                 <tbody id="approvalTable"></tbody>
                              </table>
                           </div>
                        </div>
                     </div>
                     <br>
                     <button id="btnProcess"
                        class="btn btn-primary" disabled>
                     Process Payroll
                     </button>
                  </form>
               </div>
            </div>
         </div>
      </div>
      @include('layout.footer')
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="{{asset('vendor/jquery/select2.min.js')}}"></script>
      <script>
         $("#period_id").select2({
         allowClear:true,
         placeholder:'Pilih Periode Payroll'
         });
      </script>
      <script>
/*
================================================
LOAD APPROVAL WHEN PERIOD SELECTED
================================================
*/

$('#period_id').on('change', function(){

    let periodId = $(this).val();

    $('#approvalBox').hide();
    $('#btnProcess').prop('disabled', true);

    if(!periodId) return;

    $('#approvalBox').show();
    $('#approvalLoading').show();
    $('#approvalTable').html('');

    $.get('/payroll-process/approval/'+periodId,function(res){

        let html='';
        let allFinish=true;

        /*
        =========================================
        APPROVAL DATA
        =========================================
        */

        let approvals = res.approval ?? [];
        let invalidContracts = res.invalid_contracts ?? [];

        /*
        =========================================
        APPROVAL EMPTY
        =========================================
        */

        if(approvals.length===0){

            html=`<tr>
                    <td colspan="3" class="text-center text-danger">
                        Approval belum tersedia
                    </td>
                  </tr>`;

            allFinish=false;
        }

        /*
        =========================================
        LOOP APPROVAL
        =========================================
        */

        approvals.forEach(row=>{

            let badge='';
            let approved='-';

            if(row.status==='finish'){

                badge=`<span class="badge badge-success">Finish</span>`;
                approved=row.approved_at ?? '-';

            }else{

                badge=`<span class="badge badge-warning">Pending</span>`;
                allFinish=false;
            }

            html+=`
                <tr>
                    <td>${row.payroll_component.toUpperCase()}</td>
                    <td>${badge}</td>
                    <td>${approved}</td>
                </tr>
            `;
        });

        /*
        =========================================
        INVALID CONTRACT CHECK
        =========================================
        */

        if(invalidContracts.length > 0){

            allFinish = false;

            let employeeList = '';

            invalidContracts.forEach(emp=>{

                let empName = emp.NAMA_KARYAWAN ?? 'Kontrak tidak valid';

                employeeList += `
                    <li>
                        ${emp.NPK} - ${empName}
                    </li>
                `;
            });

            html += `
                <tr>
                    <td colspan="3">

                        <div class="alert alert-danger mb-0">

                            <b>Kontrak Karyawan Tidak Valid :</b>
                            <br>
                            <b>Total Karyawan : ${invalidContracts.length}</b>

                            <br><br>

                            <div style="
                                max-height:250px;
                                overflow-y:auto;
                                border:1px solid #ddd;
                                padding:10px;
                                background:#fff;
                            ">

                                <ul style="margin-bottom:0;padding-left:20px">
                                    ${employeeList}
                                </ul>

                            </div>

                        </div>

                    </td>
                </tr>
            `;
        }

        $('#approvalTable').html(html);
        $('#approvalLoading').hide();

        /*
        ====================================
        ENABLE BUTTON ONLY IF ALL FINISH
        ====================================
        */

        if(allFinish){

            $('#btnProcess')
                .prop('disabled',false)
                .removeClass('btn-secondary')
                .addClass('btn-primary');

        }else{

            $('#btnProcess')
                .prop('disabled',true)
                .removeClass('btn-primary')
                .addClass('btn-secondary');
        }

    });

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

    let url = "{{ route('payroll-process.process') }}";

    let progressUrlTemplate =
        "{{ route('payroll.process.progress', ':period_id') }}";

    let progressUrl =
        progressUrlTemplate.replace(':period_id', periodId);

    /*
    ==========================================
    CONFIRM
    ==========================================
    */

    Swal.fire({
        title: "Generate Payroll?",
        text: "The payroll process will begin. This may take a few minutes depending on the amount of data. Do you want to proceed?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, generate!"
    }).then((result)=>{

        if(!result.isConfirmed) return;

        Swal.fire({
            title: "Payroll is being processed!",
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
                                    title:'Payroll Finished',
                                    text:'Payroll Successfully Calculated!'
                                }).then(()=>{
                                    window.location.href = "{{ route('payroll-process.index') }}";
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