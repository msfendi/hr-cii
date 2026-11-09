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
            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Kelola Pertanyaan Kuesioner Kepuasan</h1>
                    <div>
                        <a href="{{ route('satisfaction-survey.admin.results') }}" class="btn btn-sm btn-info"><i class="fas fa-chart-bar"></i> Lihat Hasil</a>
                        <a href="{{ route('satisfaction-survey.admin.export') }}" class="btn btn-sm btn-success"><i class="fas fa-file-excel"></i> Export Excel</a>
                    </div>
                    </div>

                @if ($message = Session::get('success'))
                    <div class="alert alert-success">{{ $message }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif

                <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Tambah Pertanyaan</h6></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('satisfaction-survey.admin.store') }}">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-2"><label>Bagian</label><input name="section" class="form-control" required placeholder="B / C / D / E / F / G"></div>
                                <div class="form-group col-md-2"><label>Tipe</label>
                                    <select name="question_type" class="form-control"><option value="likert">Likert 1–5</option><option value="open">Open (teks bebas)</option></select>
                                </div>
                                <div class="form-group col-md-2"><label>Urutan</label><input name="sort_order" type="number" class="form-control" placeholder="otomatis"></div>
                                <div class="form-group col-md-6"><label>Pertanyaan</label><input name="question_text" class="form-control" required></div>
                            </div>
                            <div class="form-check mb-3"><input type="checkbox" name="is_active" value="1" checked class="form-check-input" id="newActive"><label class="form-check-label" for="newActive">Aktif</label></div>
                            <button class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        </form>
                    </div>
                </div>
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm" id="dataTable">
                                <thead><tr><th width="50">ID</th><th width="70">Bagian</th><th>Pertanyaan</th><th width="100">Tipe</th><th width="80">Urutan</th><th width="90">Aktif</th><th width="160">Aksi</th></tr></thead>
                                <tbody>
                                @foreach($questions as $q)
                                    <tr>
                                        <td>{{ $q->id }}</td>
                                        <td>{{ $q->section }}</td>
                                        <td>{{ $q->question_text }}</td>
                                        <td>{{ $q->question_type === 'likert' ? 'Likert 1–5' : 'Open' }}</td>
                                        <td>{{ $q->sort_order }}</td>
                                        <td>{!! $q->is_active ? '<span class="badge badge-success">Ya</span>' : '<span class="badge badge-secondary">Tidak</span>' !!}</td>
                                        <td>
                                            <form method="POST" action="{{ route('satisfaction-survey.admin.toggle', $q->id) }}" style="display:inline" class="form-toggle">@csrf<button type="button" class="btn btn-sm btn-toggle-aktif {{ $q->is_active ? 'btn-success' : 'btn-secondary' }}" data-id="{{ $q->id }}" data-active="{{ $q->is_active ? 1 : 0 }}" data-text="{{ e($q->question_text) }}" title="{{ $q->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="fas fa-{{ $q->is_active ? 'power-off' : 'check' }}"></i> {{ $q->is_active ? 'On' : 'Off' }}</button></form>
                                            <button class="btn btn-primary btn-sm btn-edit" data-id="{{ $q->id }}" data-section="{{ $q->section }}" data-text="{{ e($q->question_text) }}" data-type="{{ $q->question_type }}" data-order="{{ $q->sort_order }}" data-active="{{ $q->is_active ? 1 : 0 }}" data-toggle="modal" data-target="#editModal"><i class="fas fa-edit"></i></button>
                                            <form method="POST" action="{{ route('satisfaction-survey.admin.destroy', $q->id) }}" style="display:inline" onsubmit="return confirm('Hapus pertanyaan dan jawabannya?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></form>
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
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        @include('layout.footer')
    </div>
</div>

<div class="modal fade" id="editModal"><div class="modal-dialog"><div class="modal-content">
    <form id="editForm" method="POST">
        @csrf @method('PUT')
        <div class="modal-header"><h5 class="modal-title">Edit Pertanyaan</h5><button class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <div class="form-group"><label>Bagian</label><input name="section" class="form-control" required></div>
            <div class="form-group"><label>Pertanyaan</label><input name="question_text" class="form-control" required></div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>Tipe</label>
                    <select name="question_type" class="form-control"><option value="likert">Likert 1–5</option><option value="open">Open</option></select>
                </div>
                <div class="form-group col-md-6"><label>Urutan</label><input name="sort_order" type="number" class="form-control"></div>
            </div>
            <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="editActive"><label class="form-check-label" for="editActive">Aktif</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
    </form>
</div></div></div>

<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script>
$(function(){
  $('#dataTable').DataTable({ pageLength:25, order:[[4,'asc']] });
  $('.btn-toggle-aktif').on('click', function(){
    const btn = $(this);
    const form = btn.closest('form.form-toggle');
    const isActive = btn.data('active') == 1;
    const text = btn.data('text');
    Swal.fire({
      title: isActive ? 'Nonaktifkan pertanyaan?' : 'Aktifkan pertanyaan?',
      text: text,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: isActive ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
      cancelButtonText: 'Batal',
      danger: isActive
    }).then((result)=>{
      if(result.isConfirmed){ form.submit(); }
    });
  });
  $('.btn-edit').click(function(){
    const b = $(this);
    const id = b.data('id');
    $('#editForm').attr('action', '/satisfaction-survey/admin/' + id);
    $('#editForm [name=\"section\"]').val(b.data('section'));
    $('#editForm [name=\"question_text\"]').val(b.data('text'));
    $('#editForm [name=\"question_type\"]').val(b.data('type'));
    $('#editForm [name=\"sort_order\"]').val(b.data('order'));
    $('#editActive').prop('checked', !!b.data('active'));
  });
});
</script>
</body>
</html>
