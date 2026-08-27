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
.role-filter-modal {
    display: flex;
    align-items: center;
}

#filterRoleModal {
    width: 250px !important;
    min-width: 250px;
}

.dataTables_filter {
    margin-left: auto;
}

.insentif-summary-card {
    border-left: 4px solid #4e73df;
}
.insentif-summary-card.highest {
    border-left-color: #1cc88a;
}
.insentif-summary-card.lowest {
    border-left-color: #e74a3b;
}
.insentif-summary-card.average {
    border-left-color: #36b9cc;
}
.insentif-summary-label {
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #b7791f;
}
.insentif-chart-card canvas {
    max-height: 280px;
}
.insentif-detail-table th,
.insentif-detail-table td {
    font-size: .8rem;
    padding: .4rem .5rem;
    vertical-align: middle;
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
                              {{-- ================= ACTION ================= --}}
                              <td class="text-center">
                                 @php
                                 $progress=collect($row->progress);
                                 $currentIndex=$progress->search(fn($i)=>$i['status']!=='approve');
                                 $canApprove=false;
                                 if($currentIndex!==false){
                                 $current=$progress[$currentIndex];
                                 $npkList=is_array($current['npk'])
                                 ? $current['npk']
                                 : json_decode($current['npk'],true);
                                 $statusList=$current['status']=='pending'
                                 ? array_fill(0,count($npkList),'waiting')
                                 : (json_decode($current['status'],true)
                                 ?? array_fill(0,count($npkList),'waiting'));
                                 foreach($npkList as $idx=>$npk){
                                 $beforeApproved=true;
                                 for($i=0;$i<$idx;$i++){
                                 if($statusList[$i]!=='approve')
                                 $beforeApproved=false;
                                 }
                                 if(
                                 $npk==auth()->user()->npk &&
                                 $statusList[$idx]!='approve' &&
                                 $beforeApproved
                                 ){
                                 $canApprove=true;
                                 }
                                 }
                                 }
                                 @endphp
                                 @if($canApprove)
                                 <button class="btn btn-success btn-sm btn-approve"
                                    data-id="{{ $row->id }}">
                                 <i class="fas fa-check"></i> Approve
                                 </button>
                                 @elseif($row->status=='finish')
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

                  <!-- ================= SUMMARY CARDS ================= -->
                  <div class="row mb-3" id="insentifSummaryRow">
                     <div class="col-md-4 mb-2">
                        <div class="card shadow h-100 py-2 insentif-summary-card highest">
                           <div class="card-body">
                              <div class="insentif-summary-label">Insentif Tertinggi</div>
                              <div class="h6 mb-0 font-weight-bold text-gray-800" id="summaryHighest">-</div>
                           </div>
                        </div>
                     </div>
                     <div class="col-md-4 mb-2">
                        <div class="card shadow h-100 py-2 insentif-summary-card lowest">
                           <div class="card-body">
                              <div class="insentif-summary-label">Insentif Terendah</div>
                              <div class="h6 mb-0 font-weight-bold text-gray-800" id="summaryLowest">-</div>
                           </div>
                        </div>
                     </div>
                     <div class="col-md-4 mb-2">
                        <div class="card shadow h-100 py-2 insentif-summary-card average">
                           <div class="card-body">
                              <div class="insentif-summary-label">Rata-rata Insentif</div>
                              <div class="h6 mb-0 font-weight-bold text-gray-800" id="summaryAverage">-</div>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- ================= CHARTS ================= -->
                  <div class="row mb-3">
                     <div class="col-md-6 mb-2">
                        <div class="card shadow h-100 insentif-chart-card">
                           <div class="card-header py-2">
                              <b class="text-primary">Distribusi Berdasarkan Role</b>
                           </div>
                           <div class="card-body">
                              <canvas id="roleChart"></canvas>
                           </div>
                        </div>
                     </div>
                     <div class="col-md-6 mb-2">
                        <div class="card shadow h-100 insentif-chart-card">
                           <div class="card-header py-2">
                              <b class="text-primary">Pengelompokan Nominal Insentif</b>
                           </div>
                           <div class="card-body">
                              <canvas id="amountChart"></canvas>
                           </div>
                        </div>
                     </div>
                  </div>

                  <div class="table-responsive">
                     <table class="table table-bordered table-sm insentif-detail-table"
                        id="insentifTable"
                        width="100%">
                        <thead>
                           <tr>
                              <th>NPK</th>
                              <th>Name</th>
                              <th>Role</th>
                              <th>Line</th>
                              <th>Insentif</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                           <tr style="background:#f8f9fc;font-weight:bold">
                              <th colspan="4" class="text-right">TOTAL</th>
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
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
      <script>
         let insentifTable;
         let roleChartInstance = null;
         let amountChartInstance = null;

         // datalabels aktif secara global supaya nilai/percentage
         // langsung tampil di chart tanpa perlu hover
         if (typeof Chart !== 'undefined' && typeof ChartDataLabels !== 'undefined') {
             Chart.register(ChartDataLabels);
         }

         function formatRupiah(number){
         
         number = Number(number) || 0;
         
         return new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:2
         }).format(number);
         
         }

         // ambil nilai insentif dari row apapun jenis komponennya
         function getInsentifValue(row){
             return row.sewing_insentif ??
                 row.pad_insentif ??
                 row.cutting_insentif ??
                 row.heat_insentif ??
                 row.sixs_insentif ??
                 0;
         }

         /*
         | ==============================================
         | RENDER SUMMARY + CHART (ROLE & NOMINAL)
         | ==============================================
         */
         function renderInsentifCharts(data){

             let values = data.map(function(r){
                 return Object.assign({}, r, { value: Number(getInsentifValue(r)) });
             });

             if (values.length === 0) {
                 $('#summaryHighest, #summaryLowest, #summaryAverage').text('-');
                 if (roleChartInstance) { roleChartInstance.destroy(); roleChartInstance = null; }
                 if (amountChartInstance) { amountChartInstance.destroy(); amountChartInstance = null; }
                 return;
             }

             // ============ TERTINGGI / TERENDAH / RATA-RATA ============
             let highest = values.reduce((a, b) => b.value > a.value ? b : a);
             let lowest  = values.reduce((a, b) => b.value < a.value ? b : a);
             let total   = values.reduce((s, r) => s + r.value, 0);
             let average = total / values.length;

             $('#summaryHighest').html(
                 formatRupiah(highest.value) +
                 '<br><small class="text-gray-600">' + highest.npk + ' - ' + highest.name + '</small>'
             );
             $('#summaryLowest').html(
                 formatRupiah(lowest.value) +
                 '<br><small class="text-gray-600">' + lowest.npk + ' - ' + lowest.name + '</small>'
             );
             $('#summaryAverage').html(formatRupiah(average));

             // ============ CHART 1: PENGELOMPOKAN BERDASARKAN ROLE ============
             let roleGroups = {};
             values.forEach(function(r){
                 let role = r.role || 'Tanpa Role';
                 if (!roleGroups[role]) roleGroups[role] = { count: 0, total: 0 };
                 roleGroups[role].count += 1;
                 roleGroups[role].total += r.value;
             });

             let roleLabels = Object.keys(roleGroups);
             let roleCounts = roleLabels.map(l => roleGroups[l].count);
             let roleColors = roleLabels.map((_, i) => `hsl(${(i * 57) % 360}, 65%, 55%)`);

             if (roleChartInstance) roleChartInstance.destroy();
             roleChartInstance = new Chart(document.getElementById('roleChart'), {
                 type: 'doughnut',
                 data: {
                     labels: roleLabels,
                     datasets: [{
                         data: roleCounts,
                         backgroundColor: roleColors
                     }]
                 },
                 options: {
                     responsive: true,
                     maintainAspectRatio: true,
                     plugins: {
                         legend: { position: 'bottom' },
                         datalabels: {
                             color: '#fff',
                             font: { weight: 'bold', size: 11 },
                             formatter: function(value, ctx){
                                 let arr = ctx.chart.data.datasets[0].data;
                                 let sum = arr.reduce((a, b) => a + b, 0);
                                 let pct = sum ? ((value / sum) * 100).toFixed(1) : 0;
                                 return value + ' orang\n(' + pct + '%)';
                             }
                         },
                         tooltip: {
                             callbacks: {
                                 label: function(ctx){
                                     let role = ctx.label;
                                     let total = roleGroups[role].total;
                                     return ctx.parsed + ' orang - Total ' + formatRupiah(total);
                                 }
                             }
                         }
                     }
                 }
             });

             // ============ CHART 2: PENGELOMPOKAN BERDASARKAN NOMINAL ============
             let amountGroups = {};
             values.forEach(function(r){
                 let key = r.value;
                 amountGroups[key] = (amountGroups[key] || 0) + 1;
             });

             let sortedAmounts = Object.keys(amountGroups).map(Number).sort((a, b) => a - b);
             let amountLabels = sortedAmounts.map(v => formatRupiah(v));
             let amountCounts = sortedAmounts.map(v => amountGroups[v]);

             if (amountChartInstance) amountChartInstance.destroy();
             amountChartInstance = new Chart(document.getElementById('amountChart'), {
                 type: 'bar',
                 data: {
                     labels: amountLabels,
                     datasets: [{
                         label: 'Jumlah Orang',
                         data: amountCounts,
                         backgroundColor: '#4e73df'
                     }]
                 },
                 options: {
                     responsive: true,
                     maintainAspectRatio: true,
                     layout: {
                         padding: { top: 28 }
                     },
                     scales: {
                         y: {
                             beginAtZero: true,
                             ticks: { precision: 0 },
                             suggestedMax: Math.max(...amountCounts) + 1
                         },
                         x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } }
                     },
                     plugins: {
                         legend: { display: false },
                         datalabels: {
                             anchor: 'end',
                             align: 'top',
                             offset: 4,
                             clamp: true,
                             clip: false,
                             color: '#2e2e2e',
                             font: { weight: 'bold', size: 11 },
                             formatter: function(value){ return value + ' orang'; }
                         }
                     }
                 }
             });
         }

         $(document).ready(function(){
         
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
        "<'col-auto role-filter-modal'>" +
        "<'col-auto ml-2'l>" +
        "<'col text-end'f>" +
    ">" +
    "rtip",

    lengthMenu: [ [10, 25, 50, 100, -1], [10, 25, 50, 100, 'All'] ],

    initComplete: function () {

        // =========================
        // CREATE SELECT ROLE
        // =========================
        let roleDropdownModal = `
        <select id="filterRoleModal"
                class="form-control form-control-sm"
                style="width:250px">
            <option value="">Role</option>
        </select>
    `;

    $('.role-filter-modal').html(roleDropdownModal);

    $('#filterRoleModal').select2({
        placeholder: 'Role',
        allowClear: true,
        width: '250px',
        dropdownParent: $('#detailModal')
    });

        // =========================
        // FILTER EVENT
        // =========================
        $(document).on('change', '#filterRoleModal', function () {

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
        {
            data:'role',
            render:function(data){
                return data ?? '-';
            }
        },
        {
            data:'line_info',
            defaultContent:'-',
            render:function(data){
                return data ?? '-';
            }
        },
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
         
         $(api.column(4).footer())
         .html(formatRupiah(total));
         
         }
         
         });
         
         });
         
         
         // label & warna badge untuk tiap jenis insentif
         // supaya modal langsung jelas ini insentif apa
         const insentifLabels = {
             'sewing_insentif':  { label: 'Sewing',    color: '#4e73df' },
             'pad_insentif':     { label: 'Pad Print',  color: '#1cc88a' },
             'cutting_insentif': { label: 'Cutting',    color: '#f6c23e' },
             'heat_insentif':    { label: 'Heat Seal',  color: '#e74a3b' },
             'sixs_insentif':    { label: '6S',         color: '#36b9cc' }
         };

         $(document).on('click','.btn-detail',function(){
         
         let period=$(this).data('period');
         let periodName=$(this).data('period-name');
         let component=$(this).data('component');

         // =========================
         // BERSIHKAN DATA LAMA DULU
         // supaya saat modal dibuka tidak sempat
         // menampilkan data dari insentif sebelumnya
         // =========================
         insentifTable
             .search('')
             .columns().search('')
             .clear()
             .draw();

         $('#filterRoleModal')
             .html('<option value="">Role</option>')
             .val('')
             .trigger('change.select2');

         renderInsentifCharts([]); // reset summary tertinggi/terendah/rata-rata & hapus chart lama
         
         $('#detailModal').modal('show');

         let info = insentifLabels[component] || { label: component, color: '#858796' };

         $('#detail-title').html(
             '<span class="badge mr-2" style="background:' + info.color + ';color:#fff;font-size:.8rem;vertical-align:middle;">'
             + info.label.toUpperCase() +
             '</span>' +
             '<span style="vertical-align:middle;">Insentif ' + info.label + ' - ' + periodName + '</span>'
         );
         
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

         // BUILD ROLE OPTIONS
         let roleMap = {};

            res.data.forEach(item => {
                let roleDisplay = item.role || 'Tanpa Role';
                roleMap[roleDisplay] = item.role || '';
            });

            let options = `<option value="">Role</option>`;

            Object.keys(roleMap).forEach(display => {
                options += `<option value="${roleMap[display]}">${display}</option>`;
            });

$('#filterRoleModal')
    .html(options)
    .val('')
    .trigger('change.select2');

         // BUILD SUMMARY + CHARTS
         renderInsentifCharts(res.data);
         
         $('#insentifTable_processing').hide();
         
         },
         
         error:function(xhr){
         
         console.log(xhr.responseText);
         $('#insentifTable_processing').hide();
         
         }
         
         });
         
         });
         
      </script>
      <script>
         /* ======================================
         APPROVE
         ====================================== */
         
         $('.btn-approve').click(function(){
         
         let btn=$(this);
         btn.prop('disabled',true);
         
         let id=$(this).data('id');
         
         Swal.fire({
         title:'Approve Insentif?',
         icon:'question',
         showCancelButton:true
         }).then((result)=>{
         
         if(result.isConfirmed){
         
         $.ajax({
         url:'/insentif-approve/'+id+'/approve',
         type:'POST',
         data:{
         _token:'{{ csrf_token() }}',
         npk:'{{ auth()->user()->npk }}'
         },
         success:function(res){
         
         Swal.fire({
         icon:'success',
         title:res.message,
         timer:1200,
         showConfirmButton:false
         });
         
         setTimeout(()=>location.reload(),1200);
         },
         error:function(err){
         
         Swal.fire({
         icon:'error',
         title:err.responseJSON.message,
         timer:2000,
         showConfirmButton:false
         });
         }
         });
         }
         });
         });
      </script>
   </body>
</html>