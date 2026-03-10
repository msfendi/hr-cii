<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
<div id="wrapper">
@include('layout.sidebar')
    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">
            @include('layout.navbar')
            <!-- Begin Page Content -->
            <div class="container-fluid">

                <!-- Page Heading -->
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Overtime Management</h1>
                </div>
                
                <!-- Add Button in Card Header -->
                <div class="card shadow mb-2">
                    <div class="card-header py-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Overtime Data</h6>
                            <div class="d-flex align-items-center flex-wrap">
                                <span class="mr-2 font-weight-bold text-dark" style="font-size:0.85rem;">Durasi Jam:</span>
                                <select id="duration_filter" class="form-control form-control-sm mr-2" style="width: 120px;">
                                    <option value="">Semua Jam</option>
                                    @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">{{ $i }} Jam</option>
                                    @endfor
                                </select>
                                <span class="mr-2 font-weight-bold text-dark" style="font-size:0.85rem;">Dept Group:</span>
                                <select id="dept_group_filter" class="form-control form-control-sm mr-2" style="width: 140px;">
                                    <option value="all">Semua</option>
                                    <option value="sewing">Sewing</option>
                                    <option value="non_sewing">Non-Sewing</option>
                                    <option value="staff">Staff</option>
                                </select>
                                <div class="form-inline ml-3">
                                    <label for="date" class="mr-2">Date:</label>
                                    <div class="input-group input-group-sm">
                                        <input type="month" class="form-control" name="date" id="date" value="{{ date('Y-m') }}" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                            </table>
                        </div>
                    </div>
                </div>
                <!-- Content Row -->
                
            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.3.0/js/bootstrap-datepicker.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.8.0/css/bootstrap-datepicker.css" rel="stylesheet"/>

<style>
    #dataTable thead tr:first-child th.week-header {
        background-color: #4e73df;
        color: #fff;
        text-align: center;
        font-weight: bold;
        border: 1px solid #3a5bbf;
    }
    #dataTable thead tr:first-child th.summary-header {
        background-color: #1cc88a;
        color: #fff;
        text-align: center;
        font-weight: bold;
        border: 1px solid #17a673;
    }
    #dataTable thead tr:first-child th.info-header {
        background-color: #858796;
        color: #fff;
        text-align: center;
        font-weight: bold;
    }
    .week-over {
        background-color: #dc3545 !important;
        color: #000000af !important;
    }
    /* Weekend & Holiday column highlight (merah) */
    th.col-red {
        background-color: #e74a3b !important;
        color: #fff !important;
    }
    td.col-red {
        background-color: #fce4e4 !important;
    }
</style>
<script>
    $(document).ready(function() {
        function loadTable() {
            var dateVal = $('#date').val();
            if (!dateVal) return;
            var monthVal = dateVal.substring(0, 7);

                var duration = $('#duration_filter').val();
            var deptGroup = $('#dept_group_filter').val();

            if ($.fn.DataTable.isDataTable('#dataTable')) {
                $('#dataTable').DataTable().destroy();
            }
            $('#dataTable').empty();

            // Fetch data and week metadata from backend
            $.ajax({
                url: '{{ route("overtime.calendar-data") }}',
                data: { month: monthVal, duration: duration, dept_group: deptGroup },
                dataType: 'json',
                success: function(response) {
                    var weeks = response.weeks;
                    var tableData = response.data;
                    var holidays = response.holidays || {};

                    // Build columns based on backend week metadata
                    var columns = [
                        { data: "NPK", className: "text-nowrap" },
                        { data: "NAMA_KARYAWAN", className: "text-nowrap" },
                        { data: "BAGIAN", className: "text-nowrap" }
                    ];

                    var weekColRanges = [];
                    var colIndex = 3;

                    for (var w = 0; w < weeks.length; w++) {
                        var weekStartCol = colIndex;
                        var daysMeta = weeks[w].days_meta || [];
                        for (var di = 0; di < weeks[w].days.length; di++) {
                            var dayNum = weeks[w].days[di];
                            var dayStr = dayNum.toString().padStart(2, '0');
                            var dow = daysMeta[di] ? daysMeta[di].day_of_week : -1;
                            var isWeekend = (dow === 0 || dow === 6);
                            var isHoliday = holidays.hasOwnProperty(dayNum.toString());

                            var cls = "text-center";
                            if (isWeekend || isHoliday) cls += " col-red";

                            columns.push({
                                data: monthVal + '-' + dayStr,
                                defaultContent: "",
                                className: cls
                            });
                            colIndex++;
                        }
                        weekColRanges.push({
                            startCol: weekStartCol,
                            endCol: colIndex - 1,
                            key: weeks[w].key
                        });
                    }

                    // Summary columns
                    columns.push({ data: "total_kehadiran", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "1", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "2", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "total", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "lembur_khusus", className: "text-center font-weight-bold", defaultContent: "0" });

                    // Tambahkan kolom untuk counting yang value nya character misal CT, MA
                    columns.push({ data: "CT", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "MA", className: "text-center font-weight-bold", defaultContent: "0" });
                    columns.push({ data: "P1", className: "text-center font-weight-bold", defaultContent: "0" });

                    // Build table header from backend week metadata
                    var theadHtml = '<thead>';

                    // Row 1: Week labels
                    theadHtml += '<tr>';
                    theadHtml += '<th class="info-header" rowspan="2">NPK</th>';
                    theadHtml += '<th class="info-header" rowspan="2">NAMA</th>';
                    theadHtml += '<th class="info-header" rowspan="2">BAGIAN</th>';
                    for (var w = 0; w < weeks.length; w++) {
                        theadHtml += '<th class="week-header" colspan="' + weeks[w].days.length + '">' + weeks[w].label + '</th>';
                    }
                    theadHtml += '<th class="summary-header" colspan="7">Summary</th>';
                    theadHtml += '</tr>';

                    // Row 2: Individual dates + summary names
                    theadHtml += '<tr>';
                    for (var w = 0; w < weeks.length; w++) {
                        var daysMeta = weeks[w].days_meta || [];
                        for (var di = 0; di < weeks[w].days.length; di++) {
                            var dayNum = weeks[w].days[di];
                            var dow = daysMeta[di] ? daysMeta[di].day_of_week : -1;
                            var isWeekend = (dow === 0 || dow === 6);
                            var isHoliday = holidays.hasOwnProperty(dayNum.toString());

                            var thCls = 'text-center';
                            var tip = '';
                            if (isWeekend) {
                                thCls += ' col-red';
                                tip = ' title="' + (dow === 0 ? 'Minggu' : 'Sabtu') + '"';
                            } else if (isHoliday) {
                                thCls += ' col-red';
                                var s = holidays[dayNum.toString()];
                                tip = ' title="' + (Array.isArray(s) ? s.join(', ') : s) + '"';
                            }

                            theadHtml += '<th class="' + thCls + '"' + tip + '>' + dayNum.toString().padStart(2, '0') + '</th>';
                        }
                    }
                    theadHtml += '<th class="text-center">Kehadiran</th>';
                    theadHtml += '<th class="text-center">1</th>';
                    theadHtml += '<th class="text-center">2</th>';
                    theadHtml += '<th class="text-center">Total</th>';
                    theadHtml += '<th class="text-center">Lembur Khusus</th>';
                    theadHtml += '<th class="text-center">CT</th>';
                    theadHtml += '<th class="text-center">MA</th>';
                    theadHtml += '<th class="text-center">P1</th>';
                    theadHtml += '</tr>';

                    theadHtml += '</thead>';

                    // Build table
                    $('#dataTable').append(theadHtml);
                    $('#dataTable').append('<tbody></tbody>');

                    // Initialize DataTable with local data (no DataTables ajax)
                    $('#dataTable').DataTable({
                        data: tableData,
                        columns: columns,
                        pageLength: 15,
                        scrollX: true,
                        autoWidth: false,
                        ordering: true,
                        order: [], // Disable default sorting to use server-side order
                        orderCellsTop: true,
                        orderMulti: true,
                        createdRow: function(row, data, dataIndex) {
                            var $tds = $(row).find('td');
                            weekColRanges.forEach(function(wr) {
                                var val = parseFloat(data[wr.key]) || 0;
                                if (val > 16) {
                                    for (var ci = wr.startCol; ci <= wr.endCol; ci++) {
                                        $tds.eq(ci).addClass('week-over');
                                    }
                                }
                            });
                        }
                    });
                }
            });
        }

        $('#date, #duration_filter, #dept_group_filter').on('change', function() {
            loadTable();
        });

        loadTable();
    });
</script>
</html>