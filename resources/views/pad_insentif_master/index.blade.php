<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <style>
.select-period + .select2-container{
    width:200px !important;
}
</style>
   <body id="page-top">
      @include('sweetalert::alert')
      <div id="wrapper">
      @include('layout.sidebar')
      <div id="content-wrapper" class="d-flex flex-column">
         <div id="content">
            @include('layout.navbar')
            <div class="container-fluid">
               <div class="d-sm-flex align-items-center justify-content-between mb-4">
                  <h1 class="h3 mb-0 text-gray-800">Pad Print Insentif Master</h1>
                  <div>
                     <a href="{{ route('pad-insentif-master.create') }}" class="d-none d-sm-inpad-block btn btn-sm btn-primary shadow-sm">
                        <i class="fas fa-plus fa-sm text-white-50"></i> Create Pad Print Insentif Master
                     </a>
                  </div>
               </div>
               
<!-- ===================================================== -->
<!-- DETAIL INSENTIF TABLE (NEW) -->
<!-- ===================================================== -->
<div class="card shadow mb-4">

<div class="card-header py-3">

<div class="d-flex justify-content-between align-items-center">

<h6 class="m-0 font-weight-bold text-primary">
Detail Insentif Karyawan
</h6>

<select id="checkPeriod"
        class="form-control select-period">
    <option value="">Pilih Payroll Period</option>

    @foreach($periods as $period)
        <option value="{{ $period->id }}">
            {{ $period->name }}
        </option>
    @endforeach

</select>

</div>

</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-bordered table-sm"
       id="insentifTable"
       width="100%">

<thead>
<tr>
    <th>NPK</th>
    <th>Name</th>
    <th>
    Department<br>
    <input type="text"
           id="searchDept"
           class="form-control form-control-sm"
           placeholder="Search Dept">
    </th>
    <th>Insentif</th>
</tr>
</thead>

<tbody></tbody>

<tfoot>
<tr style="background:#f8f9fc;font-weight:bold">
    <th colspan="3" class="text-right">TOTAL</th>
    <th></th>
</tr>
</tfoot>

</table>

</div>

</div>
</div>


<!-- ===================================================== -->
<!-- MASTER TABLE (EXISTING) -->
<!-- ===================================================== -->
               <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <!-- KIRI -->
                        <h6 class="m-0 font-weight-bold text-primary">
                            Data Pad Print Insentif Master
                        </h6>
                        <!-- KANAN -->
                        <div class="d-flex align-items-center">
                            <!-- DOWNLOAD TEMPLATE -->
                            <a href="{{ route('pad-insentif-master.template') }}"
                                class="btn btn-info btn-sm mr-2">
                            <i class="fas fa-download"></i> Download Template
                            </a>
                            <!-- IMPORT FORM -->
                            <button class="btn btn-success btn-sm"
                                 data-toggle="modal"
                                 data-target="#importModal">
                              <i class="fas fa-file-excel"></i>
                              Import Excel Insentif
                           </button>
                        </div>
                    </div>
                    <!-- PROGRESS BAR -->
                    <div class="progress mt-3" style="height:18px; display:none;" id="uploadProgress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                            role="progressbar"
                            style="width:0%"
                            id="progressBar">
                            0%
                        </div>
                    </div>
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
                     <div class="table-responsive">
                        <table class="table table-bordered table-sm"
                           id="dataTable"
                           width="100%"
                           cellspacing="0">
                           <thead>
                              <tr>
                                 <th>ID</th>
                                <th>Period</th>
                                 <th>NPK</th>
                                 <th>Name</th>
                                 <th>Dept</th>
                                 <th>Efficiency</th>
                                 <th>Piece</th>
                                 <th>Tanggal</th>
                                 <th>Action</th>
                              </tr>
                           </thead>
                           <tbody>
                              @foreach($data as $row)
                              <tr>
                                 <td>{{ $row->id }}</td>
                                 <td>{{ $row->period }}</td>
                                 <td>{{ $row->npk }}</td>
                                 <td>{{ $row->name }}</td>
                                 <td>{{ $row->dept }}</td>
                                 <td>{{ number_format($row->efficiency,0,',','.') }}</td>
                                 <td>{{ number_format($row->piece,0,',','.') }}</td>
                                 <td>{{ $row->date }}</td>
                                 <td class="text-center">
                                    <a class="btn btn-danger btn-circle btn-sm btn-delete-payroll_master"
                                       data-delete-link="{{ route('pad-insentif-master.delete',$row->id) }}"
                                       data-npk="{{ $row->npk }}"
                                       data-toggle="modal"
                                       data-target="#deleteModal">
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
      <!-- DELETE MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Delete Record</h5>
                <button class="close" data-dismiss="modal">
                    <span>×</span>
                </button>
            </div>

            <div class="modal-body">
                <p id="modal-text-payroll_master"></p>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">
                    Tutup
                </button>

                <a id="btn-confirm" href="">
                    <button class="btn btn-danger" type="button">
                        Confirm
                    </button>
                </a>
            </div>

        </div>
    </div>
</div>

         <!-- IMPORT MODAL -->
<div class="modal fade" id="importModal">
    <div class="modal-dialog">
        <form id="importForm"
              action="{{ route('pad-insentif-master.import') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Import Pad Print Insentif
                    </h5>
                    <button class="close" data-dismiss="modal">
                        <span>×</span>
                    </button>
                </div>

                <div class="modal-body">

                    <!-- PAYROLL PERIOD -->
                    <div class="form-group">
                        <label>Payroll Period</label>

                        <select name="period_id"
                                id="periodSelect"
                                class="form-control"
                                required>

                            <option value="">-- Pilih Period --</option>

                            @foreach($periods as $period)
                                <option value="{{ $period->id }}">
                                    {{ $period->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>
                    <!-- TIPE INSENTIF -->
                    <div class="form-group">
                        <label>Tipe Insentif</label>

                        <select name="is_insentif"
                                id="insentifSelect"
                                class="form-control"
                                required>

                            <option value="">-- Pilih --</option>
                            <option value="1">Insentif</option>
                            <option value="0">No Insentif</option>

                        </select>
                    </div>

                    <!-- FILE -->
                    <div class="form-group" id="fileWrapper">
                        <label>Upload File Excel</label>

                        <input type="file"
                            name="file"
                            id="fileInput"
                            class="form-control"
                            accept=".xlsx,.xls">
                    </div>

                    <!-- PROGRESS -->
                    <div class="progress"
                         style="height:18px;display:none"
                         id="uploadProgress">

                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="progressBar"
                             style="width:0%">
                            0%
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            data-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-success">
                        Import Data
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
      @include('layout.footer')
   </body>
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function formatRupiah(number){

    if(number === null || number === undefined || number === ''){
        number = 0;
    }

    if(typeof number === 'string'){
        number = number.replace(/[^0-9\-]/g,'');
    }

    number = Number(number);

    if(isNaN(number)){
        number = 0;
    }

    return new Intl.NumberFormat('id-ID',{
        style:'currency',
        currency:'IDR',
        minimumFractionDigits:0
    }).format(number);
}
</script>

<script>

let insentifTable;

/*
|--------------------------------------------------------------------------
| INIT PAGE
|--------------------------------------------------------------------------
*/
$(document).ready(function(){

    /*
    | SELECT2
    */
    $('#checkPeriod').select2({
        placeholder:'Pilih Payroll Period',
        allowClear:true,
        width:'100%'
    });

    $('#searchDept').on('keyup change', function () {

        insentifTable
            .column(2) // kolom Department
            .search(this.value)
            .draw();

    });

    /*
    | DATATABLE INIT (EMPTY FIRST)
    */
    insentifTable = $('#insentifTable').DataTable({

        processing:true,
        searching:true,
        paging:true,
        info:false,
        autoWidth:true,
        data:[],

        /* =========================
        GLOBAL SEARCH ALL COLUMN
        ==========================*/
        search:{ smart:true },

        columns:[
            {data:'npk'},
            {data:'name'},
            {data:'dept'},

            {
                data:'pad_insentif',
                defaultContent:0,
                render:function(data){
                    return formatRupiah(data);
                }
            },
        ],

        /* =========================
        ROW COLOR TKK
        ==========================*/
        createdRow:function(row,data){

            if(data.tkk == null && data.tkk == '' && data.tkk == 0){
                $(row).addClass('table-danger');
            }

        },

        /* =========================
        AUTO TOTAL (FOLLOW SEARCH)
        ==========================*/
        footerCallback:function(row,data,start,end,display){

            let api = this.api();

            function intVal(i){

                if(i === null || i === undefined || i === '') return 0;

                if(typeof i === 'number') return i;

                if(typeof i === 'string'){
                    i = i.replace(/[Rp\s]/g,'');
                    i = i.replace(/\./g,'').replace(',', '.');
                    let num = parseFloat(i);
                    return isNaN(num) ? 0 : num;
                }

                return 0;
            }

            let total = api
                .column(3,{search:'applied'})
                .data()
                .reduce(function(a,b){
                    return intVal(a)+intVal(b);
                },0);

            $(api.column(3).footer())
                .html(formatRupiah(total));
        }

    });

});


/*
|--------------------------------------------------------------------------
| LOAD DATA WHEN PERIOD SELECTED
|--------------------------------------------------------------------------
*/
$('#checkPeriod').on('change',function(){

    let period = $(this).val();

    /*
    | CLEAR TABLE IF EMPTY
    */
    if(!period){
        insentifTable.clear().draw();
        return;
    }

    /*
    | LOADING STATE
    */
    insentifTable.clear().draw();

    Swal.fire({
        title:'Loading insentif...',
        allowOutsideClick:false,
        showConfirmButton:false,
        didOpen:()=>Swal.showLoading()
    });

    /*
    | AJAX LOAD
    */
    $.ajax({

        url:'/pad-insentif-master/'+period+'/check',
        type:'GET',
        dataType:'json',

        success:function(res){

            console.log('DATA:',res);

            insentifTable.clear();
            insentifTable.rows.add(res.data);
            insentifTable.draw();

            Swal.close();
        },

        error:function(xhr){

            console.log(xhr.responseText);

            Swal.fire({
                icon:'error',
                title:'Gagal load data'
            });
        }

    });

});
</script>
<script>

$('#insentifSelect').on('change', function(){

    let val = $(this).val();

    if(val == '1'){ // INSENTIF
        $('#fileWrapper').show();
        $('#fileInput').prop('required',true);
    }
    else if(val == '0'){ // NO INSENTIF
        $('#fileWrapper').hide();
        $('#fileInput').prop('required',false);
    }

}).trigger('change');

</script>
<script>
    $('.btn-delete-payroll_master').on('click', function () {
    
    $('#btn-confirm').attr('href', $(this).data('delete-link'));
    
    $("#modal-text-payroll_master").text(
    'Apakah anda yakin ingin menghapus data payroll NPK ' +
    $(this).data('npk') + '?'
    );
    
    });
    
         $("#periodSelect").select2({
         allowClear:true,
         placeholder:'Pilih Periode Payroll'
         });
</script>
<script>
$('#importForm').submit(function(e){

    e.preventDefault();

    let form = this;
    let formData = new FormData(form);

    // CLOSE MODAL
    $('#importModal').modal('hide');

    // SHOW LOADING
    Swal.fire({
        title: 'Import sedang diproses...',
        html: 'Mohon tunggu, jangan tutup halaman',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false, // ✅ HILANGKAN OK
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({

        xhr:function(){

            let xhr = new window.XMLHttpRequest();

            xhr.upload.addEventListener("progress",function(evt){

                if(evt.lengthComputable){

                    let percent =
                        Math.round((evt.loaded / evt.total) * 100);

                    Swal.update({
                        html: 'Uploading... ' + percent + '%'
                    });

                }

            });

            return xhr;
        },

        url:$(form).attr('action'),
        type:'POST',
        data:formData,
        processData:false,
        contentType:false,

        success:function(){

            Swal.fire({
                icon:'success',
                title:'Import berhasil',
                timer:1500,
                showConfirmButton:false // ✅ TANPA OK
            });

            setTimeout(()=>{
                location.reload();
            },1500);

        },

        error:function(xhr){

            Swal.fire({
                icon:'error',
                title:'Import gagal',
                text:xhr.responseJSON?.message ?? 'Terjadi kesalahan',
                timer:2500,
                showConfirmButton:false // ✅ TANPA OK
            });

        }

    });

});
</script>
</html>