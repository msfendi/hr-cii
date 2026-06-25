<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<style>
.select-period + .select2-container{
    width:200px !important;
}
.dataTables_wrapper .d-flex {
    justify-content: flex-end !important;
}

.dept-filter {
    margin-right: 10px;
}

.dataTables_filter {
    margin-left: 10px;
}
.select2-container {
    min-width: 200px !important;
}
.dataTables_wrapper .d-flex.mb-2 {
    width: 100%;
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

<!-- ===================================================== -->
<!-- TITLE -->
<!-- ===================================================== -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">
        Line Insentif Master
    </h1>
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
    Department
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

<h6 class="m-0 font-weight-bold text-primary">
Data Line Insentif Master
</h6>

<div>

<a href="{{ route('line-insentif-master.template') }}"
   class="btn btn-info btn-sm mr-2">
<i class="fas fa-download"></i>
Download Template
</a>

<button class="btn btn-success btn-sm"
        data-toggle="modal"
        data-target="#importModal">
<i class="fas fa-file-excel"></i>
Import Excel Insentif
</button>

</div>
</div>
</div>


<div class="card-body">

<div class="table-responsive">

<table class="table table-bordered table-sm"
       id="dataTable"
       width="100%">

<thead>
<tr>
    <th>ID</th>
    <th>Period</th>
    <th>NPK</th>
    <th>Nama Karyawan</th>
    <th>Dept</th>
    <th>Insentif Line</th>
    <th>Efficiency</th>
    <th>Tanggal</th>
</tr>
</thead>

<tbody>

@foreach($data as $row)
<tr>
<td>{{ $row->id }}</td>
<td>{{ $row->period }}</td>
<td>{{ $row->npk }}</td>
<td>{{ $row->nama }}</td>
<td>{{ $row->dept }}</td>
<td>{{ $row->line_number }}</td>
<td>{{ number_format($row->efficiency,0,',','.') }}</td>
<td>{{ $row->date }}</td>

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
              action="{{ route('line-insentif-master.import') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Import Line Insentif
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


<!-- ===================================================== -->
<!-- JS SECTION -->
<!-- ===================================================== -->

<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

      <script>
let masterTable;
        $(document).ready(function(){

            masterTable = $('#dataTable').DataTable({
                order:[[0,'desc']],
                pageLength:10,
                responsive:true,
                autoWidth:false
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
    $('.btn-delete-payroll_master').on('click', function () {
    
    $('#btn-confirm').attr('href', $(this).data('delete-link'));
    
    $("#modal-text-payroll_master").text(
    'Apakah anda yakin ingin menghapus data efficiency?'
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

<script>
let insentifTable;
    $(document).ready(function(){
    /*
    | DATATABLE INIT (WAJIB DI ATAS)
    */
    insentifTable = $('#insentifTable').DataTable({

        processing:true,
        searching:true,
        paging:true,
        info:false,
        autoWidth:true,
        data:[],

        dom:
            "<'d-flex justify-content-end align-items-center mb-2 gap-2'" +
                "<'dept-filter'>" +
                "<'ml-2'f>" +
            ">" +
            "rtip",

        search:{ smart:true },

        columns:[
            {data:'npk'},
            {data:'name'},
            {data:'dept'},
            {
                data:'sewing_insentif',
                defaultContent:0,
                render:function(data){
                    return formatRupiah(data);
                }
            },
        ],

        footerCallback:function(row,data,start,end,display){

            let api = this.api();

            function intVal(i){
                if(!i) return 0;
                if(typeof i === 'number') return i;
                return parseFloat(String(i).replace(/[^\d-]/g,'')) || 0;
            }

            let total = api.column(3,{search:'applied'})
                .data()
                .reduce((a,b)=>intVal(a)+intVal(b),0);

            $(api.column(3).footer()).html(formatRupiah(total));
        }
    });


    /*
    | DEPT DROPDOWN (SETELAH TABLE READY)
    */
    let deptDropdown = `
        <select id="filterDept" class="form-control form-control-sm select2-dept" style="width:200px">
            <option value="">Department</option>
        </select>
    `;

    $('.dept-filter').html(deptDropdown);
    $('#filterDept').select2({
        placeholder: 'Department',
        allowClear: true,
        width: '200px'
    });


    /*
    | FILTER EVENT
    */
   
    $(document).on('change', '#filterDept', function () {

        let val = $(this).val();

        if (!val) {
            insentifTable.column(2).search('').draw();
            return;
        }

        insentifTable
            .column(2)
            .search('^' + val + '$', true, false) // exact match
            .draw();
    });

    /*
    | CHECK PERIOD
    */
    $('#checkPeriod').select2({
        placeholder:'Pilih Payroll Period',
        allowClear:true,
        width:'100%'
    });

});
</script>
<script>

/*
|--------------------------------------------------------------------------
| LOAD DATA WHEN PERIOD SELECTED
|--------------------------------------------------------------------------
*/
$('#checkPeriod').on('change',function(){

    let period = $(this).val();

    if(!period){
        insentifTable.clear().draw();

        masterTable.clear().draw();
        return;
    }

    Swal.fire({
        title:'Loading insentif...',
        allowOutsideClick:false,
        showConfirmButton:false,
        didOpen:()=>Swal.showLoading()
    });

    // AJAX INSENTIF (existing)
    $.ajax({
        url:'/line-insentif-master/'+period+'/check',
        type:'GET',
        success:function(res){

            insentifTable.clear();
            insentifTable.rows.add(res.data);
            insentifTable.draw();
            // =========================
            // BUILD DEPT DROPDOWN
            // =========================
            let deptMap = {};

            res.data.forEach(item => {

                let deptDisplay = item.dept;

                if(item.line_start && item.line_end){
                    deptDisplay += ` (${item.line_start}-${item.line_end})`;
                }

                deptMap[deptDisplay] = item.dept; // simpan dept asli
            });

            let options = `<option value="">Department</option>`;

            Object.keys(deptMap).forEach(display => {
                options += `<option value="${deptMap[display]}">${display}</option>`;
            });

            $('#filterDept')
            .html(options)
            .val('')
            .trigger('change.select2');
            Swal.close();
        },

        error:function(xhr){

            Swal.fire({
                icon:'error',
                title:'Gagal load data'
            });
        }
    });

    // AJAX MASTER TABLE
    $.ajax({
        url:'/line-insentif-master/'+period+'/data',
        type:'GET',
        success:function(res){

            masterTable.clear();

            res.forEach(function(row){

                masterTable.row.add([
                    row.id,
                    row.period,
                    row.npk,
                    row.nama,
                    row.dept,
                    row.line_number,
                    Number(row.efficiency).toLocaleString('id-ID'),
                    row.date,
                ]);

            });

            masterTable.draw();
        }
    });

});
</script>

</html>