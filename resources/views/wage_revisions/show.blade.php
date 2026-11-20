<!DOCTYPE html>
<html lang="id">
@include('layout.header')

<body id="page-top">
    @include('sweetalert::alert')

    <div id="wrapper">
        @include('layout.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')

                <div class="container-fluid px-4">

                    {{-- Page Header --}}
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h4 class="mb-0 font-weight-bold text-gray-800">
                                Batch Penyesuaian Upah {{ $batch->year }}
                                @php
                                    $statusColor = [
                                        'draft' => 'secondary', 'simulated' => 'info', 'approved' => 'primary',
                                        'applying' => 'warning', 'applied' => 'success',
                                        'rolled_back' => 'dark', 'cancelled' => 'secondary',
                                    ][$batch->status] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $statusColor }} align-middle">{{ $batch->status }}</span>
                            </h4>
                            <small class="text-muted">
                                Efektif {{ $batch->effective_date ? $batch->effective_date->format('d-m-Y') : '—' }}
                                &middot; UMR acuan Rp&nbsp;{{ number_format((float) $batch->umr_new, 0, ',', '.') }}
                                &middot; Kenaikan non-staff {{ rtrim(rtrim(number_format((float) (optional($batch->ruleFor('non_staff'))->percent ?? 0), 3, ',', '.'), '0'), ',') }}%
                                &middot; Pembulatan Rp&nbsp;{{ number_format((int) (optional($batch->ruleFor('non_staff'))->round_to ?? 100), 0, ',', '.') }}
                            </small>
                        </div>
                        <div class="d-flex" style="gap:6px;">
                            <a href="{{ route('wage-revisions.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i>Daftar Batch
                            </a>
                            <a href="{{ route('wage-revisions.export', $batch->id) }}" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-excel mr-1"></i>Export Hasil
                            </a>
                        </div>
                    </div>

                    @if ($payrollLocked)
                        <div class="alert alert-danger py-2 small">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Payroll periode ini sudah dijalankan, jadi batch <strong>tidak boleh diterapkan</strong>.
                            Ubah tanggal efektif ke periode berikutnya atau batalkan payroll run lebih dulu.
                        </div>
                    @endif

                    {{-- Ringkasan --}}
                    <div class="row mb-3">
                        @foreach ([
                            ['Total Item', $stats['total'], 'primary', 'list'],
                            ['Non-Staff', $stats['non_staff'], 'info', 'users'],
                            ['Staff/Expat', $stats['staff'], 'dark', 'user-tie'],
                            ['Siap Diterapkan', $stats['valid'], 'success', 'check-circle'],
                            ['Ditolak', $stats['invalid'], 'danger', 'times-circle'],
                            ['Tidak Diproses', $stats['skipped'], 'secondary', 'minus-circle'],
                            ['Di Bawah UMR', $stats['warn_umr'], 'warning', 'exclamation-triangle'],
                            ['Lewat Ambang Lembur', $stats['warn_lembur'], 'warning', 'clock'],
                        ] as [$label, $value, $color, $icon])
                            <div class="col-xl-3 col-md-6 mb-2">
                                <div class="card border-left-{{ $color }} shadow-sm h-100 py-2">
                                    <div class="card-body py-2">
                                        <div class="text-xs font-weight-bold text-{{ $color }} text-uppercase mb-1">{{ $label }}</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            {{ $value }} <i class="fas fa-{{ $icon }} text-gray-300 float-right"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Aksi batch --}}
                    <div class="card shadow-sm mb-3">
                        <div class="card-body py-3">
                            <div class="d-flex flex-wrap align-items-center" style="gap:6px;">
                                @if ($canManageNonStaff)
                                    <form method="POST" action="{{ route('wage-revisions.generate', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Hitung ulang item non-staff?"
                                        data-confirm-text="Item non-staff dihitung ulang dari aturan batch (persentase kenaikan, pembulatan, dan UMR acuan). Item yang diketik manual atau hasil impor tidak diubah. Item yang sudah ada akan diperbarui."
                                        data-confirm-button="Ya, hitung ulang"
                                        data-confirm-icon="question"
                                        data-confirm-loading="Menghitung ulang item non-staff..."
                                        data-confirm-loading-text="Ribuan item bisa diproses beberapa saat. Jangan tutup halaman ini.">
                                        @csrf
                                        <button type="submit" class="btn btn-info btn-sm">
                                            <i class="fas fa-calculator mr-1"></i>Hitung Ulang Item Non-Staff
                                        </button>
                                    </form>
                                @endif

                                @can('approve', $batch)
                                    <form method="POST" action="{{ route('wage-revisions.approve', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Setujui batch ini?"
                                        data-confirm-text="Batch berisi {{ $stats['valid'] }} item valid akan disetujui dan dikunci, lalu siap diterapkan ke kontrak. Setelah disetujui, item tidak bisa diubah sampai batch dibatalkan."
                                        data-confirm-button="Ya, setujui batch"
                                        data-confirm-icon="question"
                                        data-confirm-loading="Menyetujui batch..."
                                        data-confirm-loading-text="Item valid sedang dikunci untuk diterapkan. Jangan tutup halaman ini.">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-check-double mr-1"></i>Setujui Batch
                                        </button>
                                    </form>
                                @endcan

                                @can('apply', $batch)
                                    <form method="POST" action="{{ route('wage-revisions.apply', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Terapkan gaji baru ke kontrak?"
                                        data-confirm-text="Gaji baru dari {{ $stats['valid'] }} item valid ditulis ke kontrak karyawan di database kepegawaian. Setelah diterapkan, gaji tidak bisa diubah lagi sebelum dibatalkan."
                                        data-confirm-button="Ya, terapkan"
                                        data-confirm-icon="question"
                                        data-confirm-loading="Menerapkan gaji ke kontrak..."
                                        data-confirm-loading-text="Gaji ditulis ke database kepegawaian. Jangan tutup halaman ini.">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" {{ $payrollLocked ? 'disabled' : '' }}>
                                            <i class="fas fa-play mr-1"></i>Terapkan ke Kontrak
                                        </button>
                                    </form>
                                @endcan

                                @can('rollback', $batch)
                                    <form method="POST" action="{{ route('wage-revisions.rollback', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Kembalikan gaji ke nilai lama?"
                                        data-confirm-text="Gaji yang sudah diterapkan pada {{ $stats['applied'] }} kontrak karyawan dikembalikan ke nilai sebelum penyesuaian."
                                        data-confirm-button="Ya, kembalikan gaji lama"
                                        data-confirm-loading="Mengembalikan gaji lama..."
                                        data-confirm-loading-text="Gaji pada kontrak dikembalikan ke nilai sebelumnya. Jangan tutup halaman ini.">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">
                                            <i class="fas fa-undo mr-1"></i>Batalkan (Kembalikan Gaji Lama)
                                        </button>
                                    </form>
                                @endcan

                                @if ($canUnlock)
                                    <form method="POST" action="{{ route('wage-revisions.unlock', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Buka kunci batch ini?"
                                        data-confirm-text="Batch kembali bisa diproses (item non-staff bisa dihitung ulang) dan item yang tadi dibatalkan kembali berstatus valid. Gaji karyawan tidak berubah oleh aksi ini."
                                        data-confirm-button="Ya, buka kunci"
                                        data-confirm-icon="warning"
                                        data-confirm-loading="Membuka kunci batch..."
                                        data-confirm-loading-text="Item dikembalikan ke status valid. Jangan tutup halaman ini.">
                                        @csrf
                                        <button type="submit" class="btn btn-dark btn-sm">
                                            <i class="fas fa-unlock mr-1"></i>Buka Kunci (Admin)
                                        </button>
                                    </form>
                                @endif

                                @can('delete', $batch)
                                    <form method="POST" action="{{ route('wage-revisions.destroy', $batch->id) }}" class="mb-0 js-confirm-submit"
                                        data-confirm-title="Hapus batch ini?"
                                        data-confirm-text="Batch beserta seluruh itemnya dihapus permanen dan tidak bisa dikembalikan. Lanjutkan?"
                                        data-confirm-button="Ya, hapus batch"
                                        data-confirm-icon="error"
                                        data-confirm-loading="Menghapus batch..."
                                        data-confirm-loading-text="Batch beserta itemnya dihapus. Jangan tutup halaman ini.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-trash-alt mr-1"></i>Hapus Batch
                                        </button>
                                    </form>
                                @endcan

                                <span class="ml-auto small text-muted">
                                    Alur: <strong>Hitung/Isi → Setujui → Terapkan</strong>. Tombol yang tidak muncul berarti role Anda tidak berwenang.
                                </span>
                            </div>

                            @if ($batch->isRolledBack() && !$canUnlock)
                                <div class="alert alert-warning py-2 small mt-3 mb-0">
                                    <i class="fas fa-lock mr-1"></i>
                                    Batch ini sudah dibatalkan (gaji lama sudah dikembalikan) dan masih <strong>terkunci</strong>.
                                    Hubungi <strong>Admin</strong> untuk membuka kunci bila memang perlu diproses lagi.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        {{-- Staff / Expat --}}
                        <div class="col-lg-5">
                            <div class="card shadow-sm mb-3">
                                <div class="card-header bg-white py-2">
                                    <strong class="small">Gaji Baru Staff / Expat (per orang)</strong>
                                </div>
                                <div class="card-body py-3">
                                    @if ($canManageStaff)
                                        <form method="POST" action="{{ route('wage-revisions.staff', $batch->id) }}">
                                            @csrf
                                            <div class="row">
                                                <div class="col-6">
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold">NPK / Nama <span class="text-danger">*</span></label>
                                                        <select name="npk" id="staffNpk" class="form-control form-control-sm" required
                                                            data-placeholder="Cari NPK atau nama karyawan">
                                                            <option value=""></option>
                                                            @foreach ($staffOptions as $option)
                                                                <option value="{{ $option['npk'] }}">{{ $option['npk'] }} — {{ $option['nama'] }}@if ($option['dept'] !== '') ({{ $option['dept'] }})@endif</option>
                                                            @endforeach
                                                        </select>
                                                        <small class="text-muted d-block">Cari pakai NPK atau nama karyawan.</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="form-group mb-2">
                                                        <label class="small font-weight-bold">Cara Input</label>
                                                        <select name="mode" class="form-control form-control-sm">
                                                            <option value="target">Gaji pokok baru</option>
                                                            <option value="delta">Nominal kenaikan</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold">Nominal (Rp) <span class="text-danger">*</span></label>
                                                <div class="input-group input-group-sm">
                                                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                                                    <input type="number" name="value" class="form-control" step="1" min="0" required>
                                                </div>
                                            </div>
                                            <div class="form-group mb-2">
                                                <label class="small font-weight-bold">Keterangan</label>
                                                <input type="text" name="note" class="form-control form-control-sm" maxlength="255">
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                                <i class="fas fa-save mr-1"></i>Hitung &amp; Simpan Item
                                            </button>
                                        </form>

                                        <hr class="my-3">

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small text-muted">Banyak data sekaligus?</span>
                                            <a href="{{ route('wage-revisions.template', $batch->id) }}" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-download mr-1"></i>Template Import
                                            </a>
                                        </div>
                                        <form method="POST" action="{{ route('wage-revisions.import', $batch->id) }}" class="js-confirm-submit"
                                            enctype="multipart/form-data"
                                            data-confirm-title="Import gaji staff/expat?"
                                            data-confirm-text="Baris di file Excel ditulis ke item batch ini. Item dengan NPK yang sama akan diperbarui, item NPK lain tidak diubah."
                                            data-confirm-button="Ya, import"
                                            data-confirm-icon="question"
                                            data-confirm-loading="Mengimpor file Excel..."
                                            data-confirm-loading-text="Baris file sedang ditulis ke item batch. Jangan tutup halaman ini.">
                                            @csrf
                                            <div class="form-group mb-2">
                                                <input type="file" name="file" class="form-control form-control-sm"
                                                    accept=".xlsx,.xls,.csv" required>
                                                <small class="text-muted">Kolom: npk, gaji_pokok_baru (atau nominal_kenaikan), keterangan.</small>
                                            </div>
                                            <button type="submit" class="btn btn-success btn-sm btn-block">
                                                <i class="fas fa-file-import mr-1"></i>Import Excel
                                            </button>
                                        </form>
                                    @else
                                        <div class="alert alert-warning py-2 small mb-0">
                                            <i class="fas fa-lock mr-1"></i>
                                            @if (!$batch->isEditable())
                                                Batch berstatus <strong>{{ $batch->status }}</strong>, jadi gaji staff/expat tidak bisa diubah lagi.
                                                @if ($batch->isRolledBack())
                                                    Buka kunci batch lewat <strong>Buka Kunci (Admin)</strong> supaya bisa diproses lagi.
                                                @elseif ($batch->isApplied() || $batch->status === 'applying')
                                                    Batalkan dulu bila memang perlu diubah.
                                                @else
                                                    Selesaikan atau hapus batch ini bila perlu mengubah gajinya.
                                                @endif
                                            @else
                                                Hanya payroll role <strong>Payroll_STAFF</strong> atau <strong>Payroll_ALL</strong> yang boleh mengisi/import gaji staff &amp; expat.
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Item --}}
                        <div class="col-lg-7">
                            <div class="card shadow-sm mb-3">
                                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                    <strong class="small">Item Penyesuaian</strong>
                                    <span class="small text-muted">Non-staff dihitung otomatis, staff/expat diketik</span>
                                </div>
                                <div class="card-body px-3 pt-3 pb-2">
                                    <div class="table-responsive">
                                        <table id="tblItem" class="table table-bordered table-hover table-sm mb-0" width="100%">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th class="pl-2">NPK</th>
                                                    <th>Segmen</th>
                                                    <th class="text-right">Gaji Lama</th>
                                                    <th class="text-right">Gaji Baru</th>
                                                    <th class="text-right">Selisih / Bulan</th>
                                                    <th class="text-center">Status</th>
                                                    <th>Catatan</th>
                                                    <th class="text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($items as $item)
                                                    @php
                                                        $itemColor = [
                                                            'draft' => 'secondary', 'valid' => 'success', 'invalid' => 'danger',
                                                            'applied' => 'primary', 'failed' => 'danger',
                                                            'rolled_back' => 'dark', 'skipped' => 'secondary',
                                                        ][$item->status] ?? 'secondary';
                                                        $notes = array_filter([
                                                            $item->message,
                                                            $item->warn_below_umr ? 'Di bawah UMR' : null,
                                                            $item->warn_overtime_threshold ? 'Melewati ambang lembur' : null,
                                                            $item->warn_column_mismatch ? 'Kolom salary juga terisi' : null,
                                                        ]);
                                                        $isDailyRate = $item->segment === 'non_staff'
                                                            && (float) $item->old_daily_salary > 0
                                                            && (float) $item->old_salary <= 0;
                                                        $oldWage = $isDailyRate ? (float) $item->old_daily_salary : (float) $item->old_salary;
                                                        $newWage = $isDailyRate ? (float) $item->new_daily_salary : (float) $item->new_salary;
                                                    @endphp
                                                    <tr>
                                                        <td class="pl-2 font-weight-bold text-nowrap">{{ $item->npk }}</td>
                                                        <td>
                                                            <span class="badge badge-light border">
                                                                {{ $item->segment === 'non_staff' ? 'non-staff' : ($item->is_expat ? 'expat' : 'staff') }}
                                                            </span>
                                                        </td>
                                                        <td class="text-right" data-order="{{ $oldWage }}">
                                                            Rp&nbsp;{{ number_format($oldWage, 0, ',', '.') }}@if ($isDailyRate)<small class="text-muted">/hari</small>@endif
                                                        </td>
                                                        <td class="text-right font-weight-bold" data-order="{{ $newWage }}">
                                                            Rp&nbsp;{{ number_format($newWage, 0, ',', '.') }}@if ($isDailyRate)<small class="text-muted">/hari</small>@endif
                                                        </td>
                                                        <td class="text-right text-success" data-order="{{ (float) $item->delta_monthly }}">
                                                            Rp&nbsp;{{ number_format((float) $item->delta_monthly, 0, ',', '.') }}
                                                        </td>
                                                        <td class="text-center"><span class="badge badge-{{ $itemColor }}">{{ $item->status }}</span></td>
                                                        <td class="small text-muted">{{ implode(' • ', $notes) ?: '—' }}</td>
                                                        <td class="text-center">
                                                            @if (in_array($item->status, ['valid', 'invalid', 'skipped', 'draft']) && $canManageStaff && $item->segment === 'staff_expat')
                                                                <form method="POST" action="{{ route('wage-revisions.item.delete', [$batch->id, $item->id]) }}"
                                                                    class="mb-0 js-confirm-submit"
                                                                    data-confirm-title="Hapus item ini?"
                                                                    data-confirm-text="Item {{ $item->npk }} dihapus dari batch. Lanjutkan?"
                                                                    data-confirm-button="Ya, hapus"
                                                                    data-confirm-loading="Menghapus item..."
                                                                    data-confirm-loading-text="Item dihapus dari batch. Jangan tutup halaman ini.">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus item">
                                                                        <i class="fas fa-trash-alt"></i>
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted py-4">
                                                            Belum ada item. Klik <strong>Hitung Ulang Item Non-Staff</strong> atau isi gaji baru staff/expat.
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>{{-- /container --}}
            </div>
            @include('layout.footer')
        </div>
    </div>

    <script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('vendor/sweetalert/sweetalert.all.js') }}"></script>
    <script>
        // Konfirmasi aksi penting (terapkan, batalkan, buka kunci, hapus) memakai SweetAlert,
        // bukan dialog bawaan browser. Select2 dipakai supaya NPK bisa dicari lewat NPK/nama.
        $(function () {
            if ($.fn.select2) {
                $('#staffNpk').select2({
                    width: '100%',
                    placeholder: $('#staffNpk').data('placeholder') || 'Cari NPK atau nama karyawan',
                    allowClear: false,
                });
            }

            @if ($items->isNotEmpty())
            if ($.fn.DataTable && $('#tblItem').length) {
                $('#tblItem').DataTable({
                    order: [],
                    pageLength: 25,
                    deferRender: true,
                    autoWidth: false,
                    columnDefs: [{ orderable: false, targets: [7] }],
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ baris',
                        info: 'Menampilkan _START_–_END_ dari _TOTAL_ item',
                        infoEmpty: 'Tidak ada item',
                        infoFiltered: '(disaring dari _MAX_ item)',
                        zeroRecords: 'Item tidak ditemukan',
                        paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' }
                    }
                });
            }
            @endif

            // Baris tabel dipindah DataTables saat paging, jadi handler submit dipasang di document
            // (delegasi) supaya tombol konfirmasi tetap berfungsi di halaman tabel mana pun.
            $(document).on('submit', '.js-confirm-submit', function (event) {
                var form = this;

                if (form.dataset.confirmDone === '1') {
                    return true;
                }

                event.preventDefault();

                Swal.fire({
                    title: form.dataset.confirmTitle || 'Lanjutkan?',
                    text: form.dataset.confirmText || '',
                    icon: form.dataset.confirmIcon || 'warning',
                    showCancelButton: true,
                    confirmButtonText: form.dataset.confirmButton || 'Ya, lanjutkan',
                    cancelButtonText: 'Batal',
                    // Dialog ini hanya dua pilihan (lanjut / batal); tombol deny bawaan SweetAlert tetap disembunyikan.
                    showDenyButton: false,
                    confirmButtonColor: '#1d3f86',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                    focusCancel: true,
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    // Tampilkan loading dulu, baru kirim form. Proses hitung ulang bisa lama
                    // (ribuan item), jadi pengguna harus melihat bahwa proses sedang berjalan.
                    Swal.fire({
                        title: form.dataset.confirmLoading || 'Memproses...',
                        text: form.dataset.confirmLoadingText || 'Mohon tunggu, jangan tutup halaman ini.',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        showCancelButton: false,
                        showDenyButton: false,
                        didOpen: function () {
                            Swal.showLoading();
                        },
                    });

                    // Beri dua frame supaya dialog loading sempat tampil sebelum halaman dikirim.
                    window.setTimeout(function () {
                        form.dataset.confirmDone = '1';
                        form.submit();
                    }, 120);
                });
            });
        });
    </script>

</body>

</html>
