<!DOCTYPE html>
<html lang="en">
   @include('layout.header')
   <style>
.select2-container {
    z-index: 99999 !important;
}
.dataTables_wrapper .row {
    align-items: center !important;
}
</style>
<style>
.dept-filter-modal {
    display: flex;
    align-items: center;
}

#filterDeptModal {
    width: 250px !important;
    min-width: 250px;
}

.dataTables_filter {
    margin-left: auto;
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
               <h1 class="h3 mb-0 text-gray-800">
                  Insentif Approval
               </h1>
            </div>
            <div class="card shadow mb-4">
               <div class="card-header py-3 d-flex justify-content-between align-items-center">

               <h6 class="m-0 font-weight-bold text-primary">
                  Data Approval Payroll
               </h6>

               <form method="GET" id="filterForm">
                  <select name="status"
                        class="form-control form-control-sm"
                        onchange="document.getElementById('filterForm').submit()">

                     <option value="all" {{ $filter=='all' ? 'selected':'' }}>
                        All
                     </option>

                     <option value="open" {{ $filter=='open' ? 'selected':'' }}>
                        Open
                     </option>

                     <option value="closed" {{ $filter=='closed' ? 'selected':'' }}>
                        Closed
                     </option>

                  </select>
               </form>

            </div>
               <div class="card-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm" id="dataTable">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Period</th>
                              <th>Payroll Component</th>
                              <th>Status</th>
                              <th>Progress</th>
                              <th>Details</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($data as $row)
                           <tr>
                              <td>{{ $row->id }}</td>
                              <td>{{ $row->period_name }}</td>
                              <td>{{ strtoupper($row->payroll_component) }}</td>
                              <td>
                                 @if($row->status=='finish')
                                 <span class="badge badge-success">Finish</span>
                                 @else
                                 <span class="badge badge-warning">Pending</span>
                                 @endif
                              </td>
                              <td>
                                 @foreach($row->progress as $p)
                                 @php
                                 $users=$p['users'];
                                 $statusList=
                                 $p['status']=='approve'
                                 ? array_fill(0,count($users),'approve')
                                 : (json_decode($p['status'],true)
                                 ?? array_fill(0,count($users),'waiting'));
                                 @endphp
                                 <div class="mb-2 p-2 border rounded bg-light">
                                    @foreach($users as $idx=>$user)
                                    @php
                                    $beforeApproved=true;
                                    for($i=0;$i<$idx;$i++){
                                    if($statusList[$i]!=='approve')
                                    $beforeApproved=false;
                                    }
                                    @endphp
                                    <div class="d-flex justify-content-between">
                                       <span>
                                       <b>{{ $user['npk'] }}</b> - {{ $user['name'] }}
                                       </span>
                                       @if($statusList[$idx]=='approve')
                                       <span class="badge badge-success">Approved</span>
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
                                 <button
                                    class="btn btn-info btn-sm btn-detail"
                                    data-period="{{ $row->period_id }}"
                                    data-period-name="{{ $row->period_name }}"
                                    data-component="{{ $row->payroll_component }}">
                                 <i class="fas fa-search"></i>
                                 </button>
                              </td>
                              <td class="text-center">
                                 @if($row->status=='finish')
                                 <span class="badge badge-success">Done</span>
                                 @else
                                 <span class="badge badge-secondary">Waiting</span>
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
      <!-- ================= MODAL DETAIL ================= -->
      <div class="modal fade" id="detailModal" tabindex="-1">
         <div class="modal-dialog modal-xl">
            <div class="modal-content">
               <div class="modal-header">
                  <h5 class="modal-title" id="detail-title">
                     Insentif Detail
                  </h5>
                  <button type="button" class="close" data-dismiss="modal">
                  <span>&times;</span>
                  </button>
               </div>
               <div class="modal-body">
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm"
                        id="insentifTable"
                        width="100%">
                        <thead>
                           <tr>
                              <th>NPK</th>
                              <th>Name</th>
                              <th>Departement</th>
                              <th>Insentif</th>
                              <th>TKK</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                           <tr style="background:#f8f9fc;font-weight:bold">
                              <th colspan="3" class="text-right">TOTAL</th>
                              <th></th>
                              <th></th>
                              <th></th>
                           </tr>
                        </tfoot>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
      <script>
         let insentifTable;
         
         function formatRupiah(number){
         
         number = Number(number) || 0;
         
         return new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:2
         }).format(number);
         
         }
         
         $(document).ready(function(){
            let deptDropdownModal = `
    <select id="filterDeptModal"
            class="form-control form-control-sm"
            style="min-width:250px; width:auto">
        <option value="">Department</option>
    </select>
`;

$('.dept-filter-modal').html(deptDropdownModal);

$('#filterDeptModal').select2({
    placeholder: 'Department',
    allowClear: true,
    width: '100%',
});

/*
| FILTER DEPT (EXACT MATCH)
*/
$(document).on('change', '#filterDeptModal', function () {

    let val = $(this).val();

    if (!val) {
        insentifTable.column(2).search('').draw();
        return;
    }

    insentifTable
        .column(2)
        .search('^' + val + '$', true, false)
        .draw();
});
         
         $('#dataTable').DataTable({
         order:[[0,'desc']],
         pageLength:10,
         responsive:true,
         autoWidth:false
         });
         
         
         insentifTable = $('#insentifTable').DataTable({

    processing:true,
    searching:true,
    paging:true,
    info:false,
    autoWidth:true,
    data:[],

    dom:
    "<'row mb-2 align-items-center px-2'" +
        "<'col-auto dept-filter-modal'>" +
        "<'col text-end'f>" +
    ">" +
    "rtip",

    initComplete: function () {

        // =========================
        // CREATE SELECT DEPT
        // =========================
        let deptDropdownModal = `
        <select id="filterDeptModal"
                class="form-control form-control-sm"
                style="width:250px">
            <option value="">Department</option>
        </select>
    `;

    $('.dept-filter-modal').html(deptDropdownModal);

    $('#filterDeptModal').select2({
        placeholder: 'Department',
        allowClear: true,
        width: '250px',
        dropdownParent: $('#detailModal')
    });

        // =========================
        // FILTER EVENT
        // =========================
        $(document).on('change', '#filterDeptModal', function () {

            let val = $(this).val();

            if (!val) {
                insentifTable.column(2).search('').draw();
                return;
            }

            insentifTable
                .column(2)
                .search('^' + val + '$', true, false)
                .draw();
        });
    },

    columns:[
        {data:'npk'},
        {data:'name'},
        {data:'dept'},
        {
            data:null,
            render:function(row){

                let value =
                row.sewing_insentif ??
                row.pad_insentif ??
                row.cutting_insentif ??
                row.heat_insentif ??
                row.sixs_insentif ??
                0;

                return formatRupiah(value);
            }
        },
        {
            data:'tkk',
            render:function(data){
                return data ?? '-';
            }
        },
        {
            data:'status',
            render:function(data){

                if(data==='Resign'){
                    return `<span class="badge bg-danger text-white">Resign</span>`;
                }

                return `<span class="badge bg-success text-white">Active</span>`;
            }
        },
    ],

         createdRow:function(row,data){

         if(data.status==='Resign'){
         $(row).addClass('table-danger');
         }

         },
         
         footerCallback:function(){
         
         let api=this.api();
         
         let total = api
         .rows({search:'applied'})
         .data()
         .reduce(function(sum,row){
         
         let val =
         row.sewing_insentif ??
         row.pad_insentif ??
         row.cutting_insentif ??
         row.heat_insentif ??
         row.sixs_insentif ??
         0;
         
         return sum + Number(val);
         
         },0);
         
         $(api.column(3).footer())
         .html(formatRupiah(total));
         
         }
         
         });
         
         });
         
         
         $(document).on('click','.btn-detail',function(){
         
         let period=$(this).data('period');
         let periodName=$(this).data('period-name');
         let component=$(this).data('component');
         
         $('#detailModal').modal('show');
         
         $('#detail-title')
         .text('Insentif Detail - '+periodName);
         
         let url='';
         
         if(component==='sewing_insentif'){
         url='/line-insentif-master/'+period+'/check';
         }
         else if(component==='pad_insentif'){
         url='/pad-insentif-master/'+period+'/check';
         }
         else if(component==='cutting_insentif'){
         url='/cutting-insentif-master/'+period+'/check';
         }
         else if(component==='heat_insentif'){
         url='/heat-insentif-master/'+period+'/check';
         }
         else if(component==='sixs_insentif'){
         url='/employee-6s-assignment/'+period+'/check';
         }
         
         $('#insentifTable_processing').show();
         
         $.ajax({
         
         url:url,
         type:'GET',
         dataType:'json',
         
         success:function(res){
         
         insentifTable.clear();
         insentifTable.rows.add(res.data);
         insentifTable.draw();
         // BUILD DEPT OPTIONS
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

$('#filterDeptModal')
    .html(options)
    .val('')
    .trigger('change.select2');
         
         $('#insentifTable_processing').hide();
         
         },
         
         error:function(xhr){
         
         console.log(xhr.responseText);
         $('#insentifTable_processing').hide();
         
         }
         
         });
         
         });
         
      </script>
   </body>
</html>