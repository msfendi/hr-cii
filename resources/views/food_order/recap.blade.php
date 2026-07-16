<!-- resources/views/food_order/recap.blade.php -->
<!DOCTYPE html>
<html lang="en">
  @include('layout.header')
  <body id="page-top">
  <div id="wrapper">
  @include('layout.sidebar')
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        @include('layout.navbar')
        <div class="container-fluid">

          <link rel="preconnect" href="https://fonts.googleapis.com">
          <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

          <style>
            .fm-kiosk{ --fm-red:#4E73DF; --fm-red-dark:#224ABE; --fm-yellow:#F6C23E; --fm-green:#1CC88A;
              --fm-danger:#E74A3B; --fm-danger-dark:#BE2617;
              --fm-dark:#5A5C69; --fm-cream:#EAECF4; --fm-white:#FFFFFF; --fm-gray:#858796; --fm-border:#E3E6F0;
              --fm-shadow:0 10px 30px rgba(78,115,223,.10); font-family:'Inter',sans-serif; color:var(--fm-dark); }
            .fm-kiosk h1,.fm-kiosk h2,.fm-kiosk h3,.fm-kiosk .fm-display{ font-family:'Baloo 2',sans-serif; }

            .fm-head{ margin-bottom:22px; }
            .fm-head h1{ font-size:1.7rem; font-weight:800; margin-bottom:2px; }
            .fm-head p{ color:var(--fm-gray); margin:0; font-size:.88rem; }

            .fm-section{ background:var(--fm-white); border:1px solid var(--fm-border); border-radius:20px;
              padding:20px 22px; margin-bottom:22px; box-shadow:var(--fm-shadow); }
            .fm-section-title{ display:flex; align-items:center; gap:10px; font-weight:700; font-size:1rem; margin-bottom:16px; }
            .fm-section-title .fm-icon{ width:32px; height:32px; border-radius:10px; background:var(--fm-red); color:#fff;
              display:flex; align-items:center; justify-content:center; font-size:.85rem; flex:none; }
            .fm-section-title .fm-icon.yellow{ background:var(--fm-yellow); color:var(--fm-dark); }
            .fm-section-title .fm-icon.green{ background:var(--fm-green); }

            .fm-kiosk .form-control{ border:1.5px solid var(--fm-border); border-radius:12px; padding:9px 12px; font-size:.88rem; height:auto; }
            .fm-kiosk .form-control:focus{ border-color:var(--fm-red); box-shadow:0 0 0 3px rgba(78,115,223,.12); }
            .fm-btn-search{ background:var(--fm-red); border:none; color:#fff; font-weight:700; border-radius:12px;
              padding:9px 18px; font-size:.88rem; box-shadow:0 6px 14px rgba(78,115,223,.25); }
            .fm-btn-search:hover{ background:var(--fm-red-dark); color:#fff; }

            .fm-lock-badge{ display:inline-flex; align-items:center; gap:8px; background:var(--fm-cream); border:1.5px solid var(--fm-border);
              color:var(--fm-dark); font-weight:700; font-size:.85rem; padding:10px 16px; border-radius:12px; }
            .fm-lock-badge i{ color:var(--fm-red); }

            .fm-summary-card{ background:var(--fm-white); border-radius:18px; padding:16px 18px; box-shadow:var(--fm-shadow);
              border-left:6px solid var(--fm-yellow); height:100%; }
            .fm-summary-card .fm-sc-canteen{ font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; color:var(--fm-gray); margin-bottom:4px; }
            .fm-summary-card .fm-sc-name{ font-weight:700; font-size:1.02rem; margin-bottom:8px; }
            .fm-summary-card .fm-sc-total{ display:inline-flex; align-items:center; gap:6px; background:#FFF3D6; color:#9A6B00;
              font-weight:700; font-size:.8rem; padding:4px 12px; border-radius:20px; }

            .fm-chart-wrap{ position:relative; height:280px; }

            .fm-kiosk table thead th{ background:var(--fm-cream); border-color:var(--fm-border); font-size:.78rem;
              text-transform:uppercase; letter-spacing:.03em; color:var(--fm-gray); }
            .fm-kiosk table td, .fm-kiosk table th{ border-color:var(--fm-border); font-size:.88rem; vertical-align:middle; }

            .fm-empty-note{ color:var(--fm-gray); font-size:.88rem; text-align:center; padding:20px; }
          </style>

          <div class="fm-kiosk">
            <div class="fm-head">
              <h1><i class="fas fa-chart-pie"></i> Rekap Pesanan Makanan</h1>
              <p>Total pesanan per menu &amp; kantin untuk pihak catering / admin.</p>
            </div>

            <div class="fm-section">
              <div class="fm-section-title"><span class="fm-icon"><i class="fas fa-filter"></i></span> Filter</div>
              <div class="form-row align-items-end">
                <div class="form-group col-md-3">
                  <label class="font-weight-bold small">Tanggal</label>
                  <input type="date" id="filterDate" class="form-control" value="{{ now()->addDay()->toDateString() }}">
                </div>

                @if($myCanteen)
                  {{-- Role user terkunci ke satu kantin -> dropdown disembunyikan, filter dipaksa di server juga --}}
                  <div class="form-group col-md-4">
                    <label class="font-weight-bold small">Kantin</label>
                    <div class="fm-lock-badge">
                      <i class="fas fa-lock"></i> {{ $myCanteen->name }} (sesuai role kamu)
                    </div>
                    <input type="hidden" id="filterCanteen" value="{{ $myCanteen->id }}">
                  </div>
                @else
                  <div class="form-group col-md-3">
                    <label class="font-weight-bold small">Kantin</label>
                    <select id="filterCanteen" class="form-control">
                      <option value="">Semua Kantin</option>
                      @foreach($canteens as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                      @endforeach
                    </select>
                  </div>
                @endif

                <div class="form-group col-md-2">
                  <button id="btnFilter" class="fm-btn-search btn-block"><i class="fas fa-search"></i> Cari</button>
                </div>
              </div>
            </div>

            <div class="row" id="summaryCards"></div>

            <div class="row">
              <div class="col-lg-{{ $myCanteen ? 12 : 7 }} mb-4">
                <div class="fm-section h-100 mb-0">
                  <div class="fm-section-title"><span class="fm-icon yellow"><i class="fas fa-chart-bar"></i></span> Total Pesanan per Menu</div>
                  <div class="fm-chart-wrap"><canvas id="menuChart"></canvas></div>
                </div>
              </div>
              @if(!$myCanteen)
                <div class="col-lg-5 mb-4">
                  <div class="fm-section h-100 mb-0">
                    <div class="fm-section-title"><span class="fm-icon green"><i class="fas fa-store"></i></span> Distribusi per Kantin</div>
                    <div class="fm-chart-wrap"><canvas id="canteenChart"></canvas></div>
                  </div>
                </div>
              @endif
            </div>

            <div class="fm-section mb-0">
              <div class="fm-section-title">
                <span class="fm-icon"><i class="fas fa-list"></i></span>
                Detail Pesanan (<span id="totalCount">0</span>)
              </div>
              <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0" id="detailTable">
                  <thead>
                    <tr><th>NPK</th><th>Menu</th><th>Kantin</th><th>Status</th></tr>
                  </thead>
                  <tbody id="detailBody"></tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div>
      <br>
  @include('layout.footer')
    </div>
  </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    const FM_COLORS = ['#4E73DF', '#1CC88A', '#36B9CC', '#F6C23E', '#E74A3B', '#858796', '#6610F2'];
    let menuChart = null;
    let canteenChart = null;
    let detailTable = null;

    function renderDetailTable(details) {
      // DataTables tidak boleh diinisialisasi dua kali di elemen yang sama,
      // jadi hancurkan instance lama dulu sebelum isi ulang <tbody> tiap reload AJAX.
      if (detailTable) {
        detailTable.destroy();
      }

      let rows = '';
      details.forEach(function(d) {
        rows += `<tr><td>${d.npk}</td><td>${d.menu}</td><td>${d.kantin}</td><td>${d.status}</td></tr>`;
      });
      $('#detailBody').html(rows);

      detailTable = $('#detailTable').DataTable({
        order: [],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']],
        language: {
          search: 'Cari:',
          lengthMenu: 'Tampilkan _MENU_ data',
          info: 'Menampilkan _START_ - _END_ dari _TOTAL_ pesanan',
          infoEmpty: 'Tidak ada data',
          infoFiltered: '(disaring dari _MAX_ total data)',
          zeroRecords: 'Tidak ada pesanan yang cocok dengan pencarian',
          emptyTable: 'Belum ada pesanan untuk tanggal ini.',
          paginate: { previous: 'Sebelumnya', next: 'Berikutnya' }
        }
      });
    }

    function loadRecap() {
      let date = $('#filterDate').val();
      let canteenId = $('#filterCanteen').val();

      $.get("{{ route('food-orders.recap.data') }}", { date: date, canteen_id: canteenId }, function(res) {
        $('#totalCount').text(res.total);

        // Summary cards
        let cards = '';
        res.summary.forEach(function(s) {
          cards += `
            <div class="col-xl-3 col-md-6 mb-4">
              <div class="fm-summary-card">
                <div class="fm-sc-canteen"><i class="fas fa-store"></i> ${s.canteen_name}</div>
                <div class="fm-sc-name">${s.menu_name}</div>
                <span class="fm-sc-total"><i class="fas fa-utensils"></i> ${s.total} porsi dipesan</span>
              </div>
            </div>`;
        });
        $('#summaryCards').html(cards || '<div class="col-12"><div class="fm-empty-note">Belum ada pesanan untuk tanggal ini.</div></div>');

        // Detail table (DataTables)
        renderDetailTable(res.details);

        // Bar chart: total per menu
        const menuLabels = res.summary.map(s => s.menu_name);
        const menuTotals = res.summary.map(s => s.total);

        if (menuChart) menuChart.destroy();
        menuChart = new Chart(document.getElementById('menuChart'), {
          type: 'bar',
          data: {
            labels: menuLabels,
            datasets: [{
              label: 'Total Pesanan',
              data: menuTotals,
              backgroundColor: '#4E73DF',
              borderRadius: 8,
              maxBarThickness: 46
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
          }
        });

        // Doughnut chart: distribution per canteen (only rendered when element exists,
        // i.e. user is not locked to a single canteen)
        const canteenCanvas = document.getElementById('canteenChart');
        if (canteenCanvas) {
          const canteenLabels = res.by_canteen.map(c => c.canteen_name);
          const canteenTotals = res.by_canteen.map(c => c.total);

          if (canteenChart) canteenChart.destroy();
          canteenChart = new Chart(canteenCanvas, {
            type: 'doughnut',
            data: {
              labels: canteenLabels,
              datasets: [{
                data: canteenTotals,
                backgroundColor: FM_COLORS,
                borderWidth: 2,
                borderColor: '#fff'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { position: 'bottom' } }
            }
          });
        }
      });
    }

    $('#btnFilter').click(loadRecap);
    $(document).ready(loadRecap);
  </script>
</html>