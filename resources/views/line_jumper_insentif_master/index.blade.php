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
                  <h1 class="h3 mb-0 text-gray-800">Line Jumper Insentif Master</h1>
               </div>

<!-- ===================================================== -->
<!-- DETAIL INSENTIF TABLE -->
<!-- ===================================================== -->
<div class="card shadow mb-4">

<div class="card-header py-3">

<div class="d-flex justify-content-between align-items-center">

<h6 class="m-0 font-weight-bold text-primary">
Detail Insentif Karyawan
<small id="qcInfo" class="text-muted ml-3"></small>
</h6>

<div class="d-flex align-items-center">

<select id="checkPeriod"
        class="form-control select-period">
    <option value="">Pilih Payroll Period</option>

    @foreach($periods as $period)
        <option value="{{ $period->id }}">
            {{ $period->name }}
        </option>
    @endforeach

</select>

<button type="button"
        id="btnExportInsentif"
        class="btn btn-success btn-sm ml-2">
    <i class="fas fa-file-excel"></i> Export Excel
</button>

</div>

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
    <th>Jumlah Operasi</th>
    <th>Insentif</th>
</tr>
</thead>

<tbody></tbody>

<tfoot>
<tr style="background:#f8f9fc;font-weight:bold">
    <th colspan="4" class="text-right">TOTAL</th>
    <th></th>
</tr>
</tfoot>

</table>

</div>

</div>
</div>


<!-- ===================================================== -->
<!-- MASTER TABLE -->
<!-- ===================================================== -->
               <div class="card shadow mb-4">
                  <div class="card-header py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <!-- KIRI -->
                        <h6 class="m-0 font-weight-bold text-primary">
                            Data Line Jumper Insentif Master
                        </h6>
                        <!-- KANAN -->
                        <div class="d-flex align-items-center">
                            <!-- DOWNLOAD TEMPLATE -->
                            <a href="{{ route('line-jumper-insentif-master.template') }}"
                                class="btn btn-info btn-sm mr-2">
                            <i class="fas fa-download"></i> Download Template
                            </a>
                            <!-- IMPORT -->
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
                                 <th>Operation</th>
                                 <th>Machine</th>
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
                                 <td>{{ $row->operation }}</td>
                                 <td>{{ $row->machine }}</td>
                                 <td class="text-center">
                                    @canRoute('line-jumper-insentif-master.delete')
                                    <a class="btn btn-danger btn-circle btn-sm btn-delete-jumper"
                                       data-delete-link="{{ route('line-jumper-insentif-master.delete', $row->id) }}"
                                       data-npk="{{ $row->npk }}"
                                       data-operation="{{ $row->operation }}"
                                       data-toggle="modal"
                                       data-target="#deleteModal">
                                    <i class="fas fa-trash"></i>
                                    </a>
                                    @endcanRoute
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
                <p id="modal-text-jumper"></p>
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
              action="{{ route('line-jumper-insentif-master.import') }}"
              method="POST"
              enctype="multipart/form-data">

            @csrf

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Import Line Jumper Insentif
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
let masterTable;

// hak hapus (sama dengan permission delete di tabel server-side)
let canDelete = false;
@canRoute('line-jumper-insentif-master.delete')
canDelete = true;
@endcanRoute

const deleteUrlTemplate = "{{ route('line-jumper-insentif-master.delete', ':id') }}";

$(document).ready(function(){

    masterTable = $('#dataTable').DataTable({
        order:[[0,'desc']],
        pageLength:10,
        responsive:true,
        autoWidth:false
    });

});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
/*
| Normalisasi angka (dipakai tampilan DataTables & export Excel
| supaya nilainya selalu sama)
*/
function normalizeRupiah(number){

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

    return number;
}

function formatRupiah(number){

    number = normalizeRupiah(number);

    return new Intl.NumberFormat('id-ID',{
        style:'currency',
        currency:'IDR',
        minimumFractionDigits:2
    }).format(number);
}

function escapeHtml(text){
    return $('<div>').text(text ?? '').html();
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

    insentifTable = $('#insentifTable').DataTable({

        processing:true,
        searching:true,
        paging:true,
        info:false,
        autoWidth:true,
        data:[],
        search:{ smart:true },

        columns:[
            {data:'npk'},
            {data:'name'},
            {data:'dept'},
            {data:'total_operation', defaultContent:0},
            {
                data:'jumper_insentif',
                defaultContent:0,
                render:function(data){
                    return formatRupiah(data);
                }
            },
        ],

        /* ROW COLOR RESIGN (TKK terisi) */
        createdRow:function(row,data){

            if(data.tkk){
                $(row).addClass('table-danger');
            }

        },

        /* AUTO TOTAL (FOLLOW SEARCH) */
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
                .column(4,{search:'applied'})
                .data()
                .reduce(function(a,b){
                    return intVal(a)+intVal(b);
                },0);

            $(api.column(4).footer())
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

    $('#qcInfo').text('');

    if(!period){
        insentifTable.clear().draw();
        masterTable.clear().draw();
        return;
    }

    insentifTable.clear().draw();

    Swal.fire({
        title:'Loading insentif...',
        allowOutsideClick:false,
        showConfirmButton:false,
        didOpen:()=>Swal.showLoading()
    });

    // AJAX DETAIL INSENTIF
    $.ajax({

        url:'/line-jumper-insentif-master/'+period+'/check',
        type:'GET',
        dataType:'json',

        success:function(res){

            insentifTable.clear();
            insentifTable.rows.add(res.data);
            insentifTable.draw();

            if(res.qc_violation !== undefined){
                $('#qcInfo').text('QC Violation: ' + res.qc_violation + '%');
            }

            Swal.close();
        },

        error:function(xhr){

            Swal.fire({
                icon:'error',
                title:'Gagal load data',
                text:xhr.responseJSON?.message ?? ''
            });
        }

    });

    // AJAX MASTER TABLE
    $.ajax({
        url:'/line-jumper-insentif-master/'+period+'/data',
        type:'GET',
        success:function(res){

            masterTable.clear();

            res.forEach(function(row){

                let action = '';

                if(canDelete){
                    action = '<a class="btn btn-danger btn-circle btn-sm btn-delete-jumper"'
                           + ' data-delete-link="' + deleteUrlTemplate.replace(':id', row.id) + '"'
                           + ' data-npk="' + escapeHtml(row.npk) + '"'
                           + ' data-operation="' + escapeHtml(row.operation) + '"'
                           + ' data-toggle="modal" data-target="#deleteModal">'
                           + '<i class="fas fa-trash"></i></a>';
                }

                masterTable.row.add([
                    row.id,
                    escapeHtml(row.period),
                    escapeHtml(row.npk),
                    escapeHtml(row.name),
                    escapeHtml(row.dept),
                    escapeHtml(row.operation),
                    escapeHtml(row.machine),
                    '<div class="text-center">' + action + '</div>'
                ]);

            });

            masterTable.draw();
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
// delegated: tombol delete juga ada di baris hasil AJAX
$(document).on('click', '.btn-delete-jumper', function () {

    $('#btn-confirm').attr('href', $(this).data('delete-link'));

    $("#modal-text-jumper").text(
        'Apakah anda yakin ingin menghapus operasi "' +
        $(this).data('operation') + '" milik NPK ' +
        $(this).data('npk') + '?'
    );

});

$("#periodSelect").select2({
    allowClear:true,
    placeholder:'Pilih Periode Payroll',
    dropdownParent:$('#importModal')
});
</script>
<script>
$('#importForm').submit(function(e){

    e.preventDefault();

    let form = this;
    let formData = new FormData(form);

    $('#importModal').modal('hide');

    Swal.fire({
        title: 'Import sedang diproses...',
        html: 'Mohon tunggu, jangan tutup halaman',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
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
                showConfirmButton:false
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
                showConfirmButton:false
            });

        }

    });

});
</script>
<script>
/*
|--------------------------------------------------------------------------
| EXPORT EXCEL - DETAIL INSENTIF KARYAWAN
|--------------------------------------------------------------------------
| Mengikuti persis isi DataTables: hasil search/filter, urutan sort,
| semua halaman (bukan hanya halaman aktif), + baris TOTAL
*/
$('#btnExportInsentif').on('click',function(){

    let rows = insentifTable
        .rows({search:'applied', order:'applied'})
        .data()
        .toArray();

    if(!rows.length){
        Swal.fire({
            icon:'warning',
            title:'Tidak ada data untuk diexport'
        });
        return;
    }

    let aoa  = [['NPK','Name','Department','Jumlah Operasi','Insentif']];
    let total = 0;

    rows.forEach(function(r){

        let val = normalizeRupiah(r.jumper_insentif);
        total += val;

        aoa.push([
            r.npk  ?? '',
            r.name ?? '',
            r.dept ?? '',
            r.total_operation ?? 0,
            val
        ]);
    });

    // BARIS TOTAL (sama dengan footer tabel)
    aoa.push(['','','','TOTAL',total]);

    let ws = XLSX.utils.aoa_to_sheet(aoa);

    // FORMAT KOLOM INSENTIF
    for(let i = 2; i <= aoa.length; i++){
        let cell = ws['E'+i];
        if(cell){
            cell.t = 'n';
            cell.z = '"Rp" #,##0.00';
        }
    }

    ws['!cols'] = [
        {wch:14},
        {wch:32},
        {wch:22},
        {wch:16},
        {wch:20}
    ];

    let wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Detail Insentif Line Jumper');

    let periodName = $('#checkPeriod option:selected').text().trim()
                        .replace(/[^\w\-]+/g,'_');

    XLSX.writeFile(
        wb,
        'Detail_Insentif_Line_Jumper_' + periodName + '.xlsx'
    );

});
</script>
</html>