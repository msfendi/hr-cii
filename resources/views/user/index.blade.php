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
                    <h1 class="h3 mb-0 text-gray-800">Daftar User</h1>
                    <div>
                    @canRoute('register.create')
                    <a href="{{ route('register.create') }}" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i
                        class="fas fa-plus fa-sm text-white-50"></i> Create User</a>
                        @endcanRoute
                    </div>
                </div>
                
                <!-- DataTales Example -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Data User</h6>
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

                        @if ($message = Session::get('warning'))
                        <div class="alert alert-warning alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif

                        @if ($message = Session::get('info'))
                        <div class="alert alert-info alert-block">
                            <button type="button" class="close" data-dismiss="alert">×</button>	
                            <strong>{{ $message }}</strong>
                        </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>NPK</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Signature</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $user)
                                    <tr>
                                        <td>{{ $user->id }}</td>
                                        <td>{{ $user->npk }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->role }}</td>
                                        <td class="text-center">
                                            @if ($user->disabled)
                                                <span class="badge badge-danger">Disabled</span>
                                            @else
                                                <span class="badge badge-success">Enabled</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-info btn-circle btn-sm btn-signature"
                                                data-user-id="{{ $user->id }}"
                                                data-user-name="{{ $user->name }}"
                                                title="Update Signature"
                                                data-toggle="modal" data-target="#signatureModal">
                                                <i class="fas fa-signature"></i>
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            @canRoute('user.assign')
                                            <a href="{{ route('user.assign', ['id' => $user->id])  }}" class="btn btn-primary btn-circle btn-sm">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            @endcanRoute
                                            @canRoute('user.detail')
                                            <a href="{{ route('user.detail', ['id' => $user->id]) }}" class="btn btn-primary btn-circle btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcanRoute
                                            @canRoute('user.toggle')
                                            @if (auth()->id() !== $user->id)
                                            <form action="{{ route('user.toggle', ['id' => $user->id]) }}" method="POST" class="d-inline form-toggle-user" data-user-name="{{ $user->name }}" data-disabled="{{ $user->disabled ? 1 : 0 }}">
                                                @csrf
                                                @if ($user->disabled)
                                                <button type="submit" class="btn btn-success btn-circle btn-sm" title="Enable user">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                                @else
                                                <button type="submit" class="btn btn-warning btn-circle btn-sm" title="Disable user">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                                @endif
                                            </form>
                                            @endif
                                            @endcanRoute
                                            @canRoute('user.delete')
                                            <a class="btn btn-danger btn-circle btn-sm btn-delete-user" data-delete-link="{{ route('user.delete', ['id' => $user->id]) }}" data-user-name="{{ $user->name }}" data-toggle="modal" data-target="#deleteModal">
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
                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->
        <!-- Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="delete-title" class="modal-title" id="exampleModalLabel">Delete Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-user"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm" href=""><button class="btn btn-primary" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>

@include('layout.footer')
</body>

{{-- ===================== MODAL SIGNATURE ===================== --}}
<style>
    /* PERBAIKAN V2 (2026-11-05): daftar file signature diberi tinggi
       maksimum + scroll internal (overflow-y:auto) supaya gambar banyak
       tidak memanjang ke bawah dan ikutscroll modal. overscroll-behavior
       mencegah chain-scroll ke modal. */
    #signatureModal .modal-dialog { max-width: 900px; }
    #signatureModal .sig-file-list {
        max-height: min(320px, 45vh);
        min-height: 120px;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
        align-content: flex-start;
    }
    #signatureModal .sig-file-item {
        cursor: pointer;
        background: #fff;
        padding: 6px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        transition: border-color .15s, box-shadow .15s, background .15s;
    }
    #signatureModal .sig-file-item:hover {
        border-color: #17a2b8;
        box-shadow: 0 0 0 2px rgba(23,162,184,.18);
    }
    #signatureModal .sig-file-item.sig-active {
        border-color: #17a2b8;
        background: #e8f4f8;
        box-shadow: 0 0 0 2px rgba(23,162,184,.28);
    }
    #signatureModal .sig-file-item img {
        max-width: 100%;
        height: 78px;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }
    #signatureModal .sig-file-item .sig-filename {
        font-size: .72rem;
        color: #64748b;
        margin-top: 6px;
        word-break: break-all;
        line-height: 1.2;
    }
    @media (max-width: 576px) {
        #signatureModal .sig-file-list {
            max-height: 38vh;
            grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
            gap: 8px;
        }
        #signatureModal .sig-file-item img { height: 56px; }
    }
</style>
<div class="modal fade" id="signatureModal" tabindex="-1" role="dialog" aria-labelledby="signatureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="signatureModalLabel">
                    <i class="fas fa-signature mr-2"></i> Update Signature — <span id="sig-user-name"></span>
                </h5>
                <button class="close text-white" type="button" data-dismiss="modal"><span>×</span></button>
            </div>
            <div class="modal-body">

                {{-- Preview signature saat ini --}}
                <div id="sig-current-wrap" class="d-none mb-3 text-center">
                    <p class="text-muted small mb-1">Signature saat ini:</p>
                    <img id="sig-current-img" src="" alt="Signature" class="border rounded"
                         style="max-height:120px;background:#fff;">
                </div>

                {{-- Tabs --}}
                <ul class="nav nav-tabs mb-3" id="sigTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-upload-link" data-toggle="tab" href="#tab-upload" role="tab">
                            <i class="fas fa-upload mr-1"></i> Upload Gambar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-draw-link" data-toggle="tab" href="#tab-draw" role="tab">
                            <i class="fas fa-pen-nib mr-1"></i> Draw Signature
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-select-link" data-toggle="tab" href="#tab-select" role="tab">
                            <i class="fas fa-folder-open mr-1"></i> Pilih dari Folder
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="sigTabContent">

                    {{-- Tab Upload --}}
                    <div class="tab-pane fade show active" id="tab-upload" role="tabpanel">
                        <div class="custom-file mb-3">
                            <input type="file" class="custom-file-input" id="sig-upload-input" accept="image/png,image/jpeg">
                            <label class="custom-file-label" for="sig-upload-input">Pilih file PNG / JPG (maks 5MB)</label>
                        </div>
                        <div id="sig-upload-preview" class="text-center d-none">
                            <img id="sig-upload-img" src="" alt="Preview"
                                 class="border rounded" style="max-height:150px;background:#fff;">
                        </div>
                    </div>

                    {{-- Tab Draw --}}
                    <div class="tab-pane fade" id="tab-draw" role="tabpanel">
                        <p class="text-muted small">Dimensi canvas: <strong>577 × 432 px</strong> (sama dengan file contoh)</p>
                        <div class="border rounded bg-white text-center" style="overflow:hidden;line-height:0;">
                            <canvas id="sig-canvas" width="577" height="432"
                                    style="cursor:crosshair;max-width:100%;touch-action:none;"></canvas>
                        </div>
                        <div class="mt-2 d-flex gap-2">
                            <button type="button" id="sig-clear-btn" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eraser mr-1"></i> Bersihkan
                            </button>
                            <span class="text-muted small ml-2 align-self-center">Gunakan mouse atau stylus untuk menggambar tanda tangan</span>
                        </div>
                    </div>

                    {{-- Tab Select --}}
                    <div class="tab-pane fade" id="tab-select" role="tabpanel">
                        <p class="text-muted small mb-2">File di folder <code>storage/app/public/signature</code>:</p>
                        {{-- PERBAIKAN V2: daftar file diberi tinggi maksimum + scroll
                             internal (lihat CSS #signatureModal .sig-file-list),
                             supaya gambar banyak tidak memanjang ke bawah. --}}
                        <div id="sig-file-list" class="sig-file-list">
                            <div class="text-muted small"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</div>
                        </div>
                        <input type="hidden" id="sig-selected-file" value="">
                    </div>

                </div>{{-- end tab-content --}}

                <div id="sig-error" class="alert alert-danger mt-3 d-none"></div>
                <div id="sig-success" class="alert alert-success mt-3 d-none"></div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                <button type="button" id="sig-save-btn" class="btn btn-info">
                    <i class="fas fa-save mr-1"></i> Simpan Signature
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>

<script>
// --- Delete modal ---
$('.btn-delete-user').on('click', function () {
    $('#btn-confirm').attr('href', $(this).data('delete-link'));
    $("#modal-text-user").text('Apakah anda yakin ingin menghapus user ' + $(this).data('user-name') + '?');
});

$(document).on('submit', '.form-toggle-user', function (e) {
    var isDisabled = parseInt($(this).data('disabled')) === 1;
    var action = isDisabled ? 'mengaktifkan' : 'menonaktifkan';
    if (!confirm('Apakah anda yakin ingin ' + action + ' user ' + $(this).data('user-name') + '?')) {
        e.preventDefault();
    }
});

// ===================== SIGNATURE MODAL =====================
var sigUserId = null;
var sigActiveTab = 'upload';

// Buka modal → load data signature saat ini
$(document).on('click', '.btn-signature', function () {
    sigUserId = $(this).data('user-id');
    $('#sig-user-name').text($(this).data('user-name'));
    $('#sig-error,#sig-success').addClass('d-none').text('');
    $('#sig-current-wrap').addClass('d-none');
    $('#sig-upload-preview').addClass('d-none');
    $('#sig-upload-input').val('');
    clearCanvas();
    $('#sig-selected-file').val('');
    // Reset tab ke Upload
    $('#sigTabs a[href="#tab-upload"]').tab('show');

    // Load signature saat ini
    $.getJSON('/user/signature/' + sigUserId, function (data) {
        if (data.url) {
            $('#sig-current-img').attr('src', data.url + '?t=' + Date.now());
            $('#sig-current-wrap').removeClass('d-none');
        }
    });

    // Load file list
    loadSigFiles();
});

// Tab draw: inisialisasi canvas sekali saat tab muncul
$('#tab-draw-link').on('shown.bs.tab', function () {
    initCanvas();
});

// --- CANVAS DRAW ---
var canvas, ctx, drawing = false, canvasInited = false;

function initCanvas() {
    if (canvasInited) return;
    canvasInited = true;
    canvas = document.getElementById('sig-canvas');
    ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    // Fill white
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    function getPos(e) {
        var r = canvas.getBoundingClientRect();
        var scaleX = canvas.width / r.width;
        var scaleY = canvas.height / r.height;
        var src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - r.left) * scaleX, y: (src.clientY - r.top) * scaleY };
    }
    canvas.addEventListener('mousedown',  function(e){ drawing=true; var p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); });
    canvas.addEventListener('mousemove',  function(e){ if(!drawing) return; var p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); });
    canvas.addEventListener('mouseup',    function(){ drawing=false; });
    canvas.addEventListener('mouseleave', function(){ drawing=false; });
    canvas.addEventListener('touchstart', function(e){ e.preventDefault(); drawing=true; var p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }, {passive:false});
    canvas.addEventListener('touchmove',  function(e){ e.preventDefault(); if(!drawing) return; var p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); }, {passive:false});
    canvas.addEventListener('touchend',   function(){ drawing=false; });
}

function clearCanvas() {
    if (!canvas) canvas = document.getElementById('sig-canvas');
    if (!ctx && canvas) ctx = canvas.getContext('2d');
    if (ctx) {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
}

$('#sig-clear-btn').on('click', clearCanvas);

// --- Upload preview ---
$('#sig-upload-input').on('change', function () {
    var file = this.files[0];
    if (!file) return;
    var label = $(this).next('.custom-file-label');
    label.text(file.name);
    var reader = new FileReader();
    reader.onload = function (e) {
        $('#sig-upload-img').attr('src', e.target.result);
        $('#sig-upload-preview').removeClass('d-none');
    };
    reader.readAsDataURL(file);
});

// --- Load file list ---
function loadSigFiles() {
    $('#sig-file-list').html('<div class="text-muted small"><i class="fas fa-spinner fa-spin mr-1"></i> Memuat...</div>');
    $.getJSON('/user/signature-files', function (files) {
        if (!files.length) {
            $('#sig-file-list').html('<div class="text-muted small">Belum ada file di folder signature.</div>');
            return;
        }
        var html = '';
        files.forEach(function (f) {
            html += '<div class="sig-file-item" data-filename="' + f.filename + '" title="Klik untuk memakai signature ini">' +
                '<img src="' + f.url + '" alt="' + f.filename + '">' +
                '<div class="sig-filename">' + f.filename + '</div>' +
                '</div>';
        });
        $('#sig-file-list').html(html);
    }).fail(function () {
        $('#sig-file-list').html('<div class="text-danger small">Gagal memuat daftar file.</div>');
    });
}

$(document).on('click', '.sig-file-item', function () {
    $('.sig-file-item').removeClass('sig-active');
    $(this).addClass('sig-active');
    $('#sig-selected-file').val($(this).data('filename'));
});

// --- Determine active tab ---
$('#sigTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
    sigActiveTab = $(e.target).attr('href').replace('#tab-','');
});

// --- SIMPAN ---
$('#sig-save-btn').on('click', function () {
    if (!sigUserId) return;
    $('#sig-error,#sig-success').addClass('d-none').text('');
    $('#sig-save-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...');

    var fd = new FormData();
    fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
    fd.append('mode', sigActiveTab);

    if (sigActiveTab === 'upload') {
        var file = $('#sig-upload-input')[0].files[0];
        if (!file) { showSigError('Pilih file gambar terlebih dahulu.'); return; }
        fd.append('file', file);

    } else if (sigActiveTab === 'draw') {
        if (!canvas) { showSigError('Canvas belum dimuat.'); return; }
        // Cek apakah canvas kosong (semua pixel putih)
        var pxData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var hasInk = false;
        for (var i = 0; i < pxData.length; i += 4) {
            if (pxData[i] < 240 || pxData[i+1] < 240 || pxData[i+2] < 240) { hasInk = true; break; }
        }
        if (!hasInk) { showSigError('Canvas masih kosong, gambar tanda tangan terlebih dahulu.'); return; }
        fd.append('image_data', canvas.toDataURL('image/png'));

    } else if (sigActiveTab === 'select') {
        var sel = $('#sig-selected-file').val();
        if (!sel) { showSigError('Pilih salah satu file dari daftar.'); return; }
        fd.append('selected_file', sel);
    }

    $.ajax({
        url: '/user/signature/' + sigUserId,
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function (res) {
            $('#sig-success').removeClass('d-none').text('Signature berhasil disimpan!');
            // Refresh preview
            $.getJSON('/user/signature/' + sigUserId, function (data) {
                if (data.url) {
                    $('#sig-current-img').attr('src', data.url + '?t=' + Date.now());
                    $('#sig-current-wrap').removeClass('d-none');
                }
            });
        },
        error: function (xhr) {
            var msg = 'Gagal menyimpan signature.';
            if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg = Object.values(xhr.responseJSON.errors).flat().join(' ');
            }
            showSigError(msg);
        },
        complete: function () {
            $('#sig-save-btn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Signature');
        }
    });
});

function showSigError(msg) {
    $('#sig-error').removeClass('d-none').text(msg);
    $('#sig-save-btn').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Simpan Signature');
}
</script>
</html>