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
            {{-- ===================================================== --}}
            {{-- PAGE HEADING --}}
            {{-- ===================================================== --}}
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
               <h1 class="h3 mb-0 text-gray-800">Compensation</h1>
               <form method="POST"
                  action="{{ route('compensation.generate') }}"
                  id="generateForm"
                  class="form-inline">
                  @csrf
                  <input type="text"
                     name="generate_date"
                     id="generate_date"
                     class="form-control form-control-sm mr-2"
                     placeholder="Select Date"
                     required readonly>
                  <button type="submit"
                     id="btnGenerate"
                     class="btn btn-primary btn-sm shadow-sm">
                  <i class="fas fa-cogs"></i> Generate Compensation
                  </button>
               </form>
            </div>
            {{-- ===================================================== --}}
            {{-- DATA TABLE --}}
            {{-- ===================================================== --}}
            <div class="card shadow mb-4">
               <div class="card-header py-3 d-flex justify-content-between align-items-center">
                  <h6 class="m-0 font-weight-bold text-primary">
                     Data Compensations
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
                     <table class="table table-bordered table-sm"
                        id="dataTable"
                        width="100%"
                        cellspacing="0">
                        <thead>
                           <tr>
                              <th>ID</th>
                              <th>Period</th>
                              <th>Process Date</th>
                              <th>Total Compensation</th>
                              <th>Employee Count</th>
                              <th>File</th>
                              <th>Bank Format</th>
                              <th>Approval Status</th>
                              <th>Action</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($compensations as $comp)
                           @php
                           $folder = \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('F_Y');
                           @endphp
                           <tr>
                              <td>{{ $comp->id }}</td>
                              <td>
                                 {{ \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('F Y') }}
                              </td>
                              <td>{{ $comp->created_at }}</td>
                              <td>
                                 Rp {{ number_format($comp->total_amount ?? 0,0,',','.') }}
                              </td>
                              <td>{{ $comp->total_employee ?? 0 }}</td>
                              <td class="text-center">
                                 @if($comp->file_pdf)
                                 <a class="btn btn-danger btn-sm"
                                    href="{{ Storage::url('compensations/' . $folder . '/' .$comp->file_pdf) }}"
                                    target="_blank">
                                 <i class="fas fa-file-pdf"></i> PDF
                                 </a>
                                 @endif
                              </td>
                              <td class="text-center">
                                 @if($comp->file_csv)
                                 <a class="btn btn-primary btn-sm"
                                    href="{{ Storage::url('compensations/' . $folder . '/' .$comp->file_csv) }}"
                                    target="_blank">
                                 <i class="fas fa-university"> CSV</i>
                                 </a>
                                 @endif
                              </td>
                              <td class="text-center">
                                 @if($comp->approve_status == 'finish')
                                 <span class="badge badge-success">Approved</span>
                                 @else
                                 <span class="badge badge-warning">
                                 <i class="fas fa-spinner fa-spin"></i> Waiting
                                 </span>
                                 @endif
                              </td>
                              <td class="text-center">
                                 <button
                                    class="btn btn-info btn-circle btn-sm btn-detail"
                                    data-date="{{ $comp->cutoff_date }}"
                                    data-period="{{ \Carbon\Carbon::parse($comp->cutoff_date)->translatedFormat('F_Y') }}">
                                 <i class="fas fa-eye"></i>
                                 </button>
                              </td>
                           </tr>
                           @endforeach
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
            {{-- ===================================================== --}}
            {{-- DETAIL TABLE --}}
            {{-- ===================================================== --}}
            <div id="comp-detail-container" style="display:none;" class="mt-4">
               <div class="card shadow">
                  <div class="card-header">
                     <h6 id="detail-title" class="m-0 font-weight-bold text-primary">
                        Compensation Details
                     </h6>
                  </div>
                  <div class="card-body">
                     <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="table-details">
                           <thead>
                              <tr>
                                 <th>ID</th>
                                 <th>NPK</th>
                                 <th>Department</th>
                                 <th>Amount</th>
                                 <th>Status</th>
                                 <th>Active</th>
                              </tr>
                           </thead>
                           <tbody></tbody>
                           <tfoot>
                              <tr style="font-weight:bold;background:#f8f9fc">
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
      </div>
      @include('layout.footer')
      {{-- ===================================================== --}}
      {{-- DATATABLE --}}
      {{-- ===================================================== --}}
      <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
      <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
         $('#dataTable').DataTable({
         order:[[0,'desc']],
         responsive:true,
         autoWidth:false
         });
      </script>
      {{-- ===================================================== --}}
      {{-- DATE PICKER --}}
      {{-- ===================================================== --}}
      <script>
         flatpickr("#generate_date",{
         dateFormat:"Y-m-d",
         disableMobile:true,
         enable:[
         function(date){
         return date.getDate()===7 || date.getDate()===20;
         }
         ]
         });
      </script>
      {{-- ===================================================== --}}
      {{-- ✅ SWEETALERT LOADING GENERATE --}}
      {{-- ===================================================== --}}
      <!-- <script>
      $(document).ready(function(){

         $('#generateForm').on('submit', function(e){

            e.preventDefault(); // stop submit dulu

            Swal.fire({
                  title: 'Generating Compensation...',
                  html: 'Please wait, system is processing data',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  didOpen: () => {
                     Swal.showLoading();
                  }
            });

            // submit setelah swal tampil
            this.submit();
         });

      });
      </script> -->
      {{-- ===================================================== --}}
      {{-- DETAIL AJAX --}}
      {{-- ===================================================== --}}
      <script>
         let tableDetails=null;
         
         $('.btn-detail').on('click',function(){
         
         let date=$(this).data('date');
         let period=$(this).data('period');
         
         $('#detail-title').text('Compensation Details ('+period+')');
         
         $('#comp-detail-container').show();
         
         if(tableDetails){
         tableDetails.destroy();
         }
         
         tableDetails=$('#table-details').DataTable({
         
         processing:true,
         responsive:true,
         
         ajax:'/compensation/details/'+date,
         
         columns:[
         {data:'id'},
         {data:'npk'},
         {data:'dept'},
         {
         data:'amount',
         render:data=>new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:0
         }).format(data ?? 0)
         },
         {data:'status'},
         {
         data:'is_active',
         render:data=>{
         return data==1
         ?'<span class="badge badge-success">Active</span>'
         :'<span class="badge badge-danger">Out</span>';
         }
         }
         ],
         
         footerCallback:function(row,data,start,end,display){
         
         let api=this.api();
         
         function intVal(i){
         
         if(i===null||i===undefined||i==='') return 0;
         
         if(typeof i==='number') return i;
         
         if(typeof i==='string'){
         i=i.replace(/[Rp\s]/g,'');
         i=i.replace(/\./g,'').replace(',','.');
         let num=parseFloat(i);
         return isNaN(num)?0:num;
         }
         
         return 0;
         }
         
         let total=api
         .column(3,{search:'applied'})
         .data()
         .reduce(function(a,b){
         return intVal(a)+intVal(b);
         },0);
         
         $(api.column(3).footer()).html(
         new Intl.NumberFormat('id-ID',{
         style:'currency',
         currency:'IDR',
         minimumFractionDigits:0
         }).format(total)
         );
         
         }
         
         });
         
         });
         
      </script>
   </body>
</html>