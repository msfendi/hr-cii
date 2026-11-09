@extends('layouts.app')

@section('title', 'Edit Kuis: ' . $quiz->title)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit text-primary mr-2"></i> Edit Kuis: {{ $quiz->title }}
        </h1>
        <div>
            <a href="{{ route('quiz.index') }}" class="btn btn-secondary btn-sm mr-2">
                <i class="fas fa-arrow-left mr-1"></i> Daftar Kuis
            </a>
            @if($quiz->status === 'draft')
                <form action="{{ route('quiz.start', $quiz->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm mr-2">
                        <i class="fas fa-play mr-1"></i> Mulai Kuis
                    </button>
                </form>
            @elseif($quiz->status === 'active')
                <a href="{{ route('quiz.live', $quiz->id) }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-chart-line mr-1"></i> Live Monitoring
                </a>
                <a href="{{ route('quiz.close', $quiz->id) }}" class="btn btn-warning btn-sm mr-2">
                    <i class="fas fa-stop mr-1"></i> Tutup Kuis
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <!-- Info Kuis -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Informasi Sesi</h6>
            <span class="badge badge-{{ $quiz->isQuiz() ? 'primary' : ($quiz->isPolling() ? 'info' : 'success') }}">
                <i class="fas fa-{{ $quiz->isQuiz() ? 'graduation-cap' : ($quiz->isPolling() ? 'chart-bar' : 'cloud') }} mr-1"></i>
                {{ $quiz->modeLabel() }}
            </span>
        </div>
        <div class="card-body">
            <form action="{{ route('quiz.update', $quiz->id) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="title" class="font-weight-bold">Judul</label>
                            <input type="text" name="title" id="title" class="form-control" value="{{ $quiz->title }}" required maxlength="200">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="font-weight-bold">Status</label>
                            <input type="text" class="form-control" value="{{ $quiz->status === 'active' ? 'Aktif' : ($quiz->status === 'closed' ? 'Ditutup' : 'Draft') }}" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description" class="font-weight-bold">Deskripsi</label>
                    <textarea name="description" id="description" class="form-control" rows="3">{{ $quiz->description }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- QR Code untuk peserta -->
    @if($quiz->status === 'active')
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-qrcode mr-2"></i> QR Code untuk Peserta (Kuis Aktif)
            </h6>
        </div>
        <!-- QR: container flex center 100% karena qrcodejs menulis <img>+<canvas> inline.
             .card-body text-center saja tidak pernah meng-center-kan img/canvas qrcodejs
             (canvas & img ukurannya inline, parent block) -> selalu tampak ke kiri di screenshot.
             Fix: override padding bawaan sb-admin-2 (1.25rem) dan paksa #qrCodeCanvas
             jadi flex center agar QR benar-benar di tengah, bukan hanya text-center. -->
        <div class="card-body" style="padding:1.5rem !important; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
            <div id="qrCodeCanvas" style="width:160px; height:160px; margin:0 auto; line-height:0;"></div>
            <p class="mt-3 mb-1" style="text-align:center; width:100%;"><strong>Kode:</strong> <code>{{ $quiz->qr_code }}</code></p>
            <p class="text-muted small mb-3" style="text-align:center; width:100%;">Peserta scan QR ini untuk bergabung ke kuis</p>
            <a href="{{ url('/quiz/join/' . $quiz->qr_code) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-external-link-alt mr-1"></i> Buka Link Peserta
            </a>
        </div>
    </div>
    @endif

    <!-- Daftar Pertanyaan -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Pertanyaan ({{ $quiz->questions_count }})</h6>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalAddQuestion">
                <i class="fas fa-plus mr-1"></i> Tambah Pertanyaan
            </button>
        </div>
        <div class="card-body">
            @if($quiz->questions->isEmpty())
                <p class="text-muted text-center py-4">Belum ada pertanyaan. Klik "Tambah Pertanyaan" untuk memulai.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Pertanyaan</th>
                                <th>Tipe</th>
                                <th>Opsi</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($quiz->questions as $idx => $q)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $q->question_text }}</td>
                                <td>
                                    <span class="badge badge-{{ $q->type === 'multiple_choice' ? 'info' : 'primary' }}">
                                        {{ $q->type === 'multiple_choice' ? 'Pilihan Ganda' : 'Pilihan Tunggal' }}
                                    </span>
                                </td>
                                <td>{{ $q->options->count() }} opsi</td>
                                <td class="text-center">
                                    <form action="{{ route('quiz.deleteQuestion', [$quiz->id, $q->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Hapus pertanyaan ini?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

<!-- Modal Tambah Pertanyaan -->
<div class="modal fade" id="modalAddQuestion" tabindex="-1" role="dialog" aria-labelledby="modalAddQuestionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('quiz.addQuestion', $quiz->id) }}" method="POST" id="formAddQuestion">
                @csrf
                <input type="hidden" name="mode_type" value="{{ $quiz->mode }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddQuestionLabel">Tambah Pertanyaan Baru ({{ $quiz->modeLabel() }})</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="form-group">
                        <label class="font-weight-bold">Teks Pertanyaan</label>
                        <textarea name="question_text" class="form-control" rows="2" required maxlength="500" placeholder="{{ $quiz->isWordCloud() ? 'Contoh: Sebutkan satu kata yang menggambarkan suasana kerja hari ini' : 'Tulis pertanyaan di sini...' }}">{{ old('question_text') }}</textarea>
                    </div>

                    @if(!$quiz->isWordCloud())
                    <div class="form-group">
                        <label class="font-weight-bold">Tipe</label>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input" id="typeSingle" name="type" value="single_choice" checked>
                            <label class="custom-control-label" for="typeSingle">Pilihan Tunggal (Satu Jawaban)</label>
                        </div>
                        @if($quiz->isQuiz())
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input" id="typeMultiple" name="type" value="multiple_choice">
                            <label class="custom-control-label" for="typeMultiple">Pilihan Ganda (Bisa Lebih dari Satu)</label>
                        </div>
                        @endif
                    </div>
                    @else
                    {{-- Word cloud: tipe terkunci open_text --}}
                    <input type="hidden" name="type" value="open_text">
                    <div class="alert alert-info small">
                        <i class="fas fa-cloud mr-1"></i> Peserta akan mengetik jawaban bebas. Kata paling sering muncul ditampilkan lebih besar pada awan kata.
                    </div>
                    @endif

                    @unless($quiz->isWordCloud())
                    <div class="form-group" id="optionsSection">
                        <label class="font-weight-bold">Opsi Jawaban <small class="text-muted">(minimal 2{{ $quiz->isQuiz() ? ', centang yang benar' : '' }})</small></label>
                        <div id="optionsContainer">
                            @for($i = 0; $i < 4; $i++)
                                <div class="input-group mb-2">
                                    @if($quiz->isQuiz())
                                    <div class="input-group-prepend">
                                        <div class="custom-control custom-checkbox">
                                            <input type="hidden" name="options[{{$i}}][is_correct]" value="0">
                                            <input type="checkbox" class="custom-control-input" name="options[{{$i}}][is_correct]" id="optCorrect{{$i}}" value="1">
                                            <label class="custom-control-label" for="optCorrect{{$i}}"><strong>Benar</strong></label>
                                        </div>
                                    </div>
                                    @else
                                    <input type="hidden" name="options[{{$i}}][is_correct]" value="0">
                                    @endif
                                    <input type="text" class="form-control" name="options[{{$i}}][text]" placeholder="Opsi {{ $i + 1 }}" value="{{ old('options.'.$i.'.text') }}" required maxlength="200">
                                </div>
                            @endfor
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddOption">
                            <i class="fas fa-plus mr-1"></i> Tambah Opsi
                        </button>
                    </div>
                    @endunless
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Pertanyaan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Scoped fix layout ganda quiz:
     layouts/app.blade.php sudah bungkus @yield('content') di .container-fluid.py-3
     (sb-admin-2: padding 1.5rem kiri/kanan). Halaman quiz menambah lagi .card.shadow.mb-4
     dan .card-body padding:1.25rem bawaan sb-admin-2. Join.blade menambah lagi
     .container + .row + .col-md-8 -> terasa double/triple padding.
     Fix global tidak aman untuk semua halaman, jadi override scoped di sini saja. -->
<style>
.container-fluid.py-3 { padding: 0.5rem 1rem !important; }
.container-fluid.py-3 > .d-flex,
.container-fluid.py-3 > .card { margin-left: 0 !important; margin-right: 0 !important; }
.card.shadow.mb-4 { margin-bottom: 1rem !important; }
/* qrcodejs menulis DUA elemen: <img> (final) + <canvas> (sumber). Semula CSS
   lama memaksa BOTH jadi display:block !important. Karena inline style
   element yang diset qrcodejs (canvas display:none) tidak bisa menalahkan
   CSS rule dengan !important, canvas jadi ikut tampil -> QR tampil 2x.
   Fix: paksa canvas selalu display:none, tampilkan hanya img-nya. */
#qrCodeCanvas { width: 160px !important; height: 160px !important; margin: 0 auto !important; }
#qrCodeCanvas canvas { display: none !important; }
#qrCodeCanvas img { display: block !important; margin: 0 auto !important; }
</style>

@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // QR Code untuk edit page (hanya saat kuis aktif)
    const qrCanvas = document.getElementById('qrCodeCanvas');
    if (qrCanvas) {
        new QRCode(qrCanvas, {
            text: '{{ url("/quiz/join/" . $quiz->qr_code) }}',
            width: 160,
            height: 160,
            colorDark: '#1e3c72',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }

    // Dynamic add option
    let optionCount = 4;
    const btnAdd = document.getElementById('btnAddOption');
    if (btnAdd) {
        btnAdd.addEventListener('click', function() {
            const container = document.getElementById('optionsContainer');
            const isQuizMode = {{ $quiz->isQuiz() ? 'true' : 'false' }};
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            const correctBlock = isQuizMode
                ? `<div class="input-group-prepend">
                       <div class="custom-control custom-checkbox">
                           <input type="hidden" name="options[\${optionCount}][is_correct]" value="0">
                           <input type="checkbox" class="custom-control-input" name="options[\${optionCount}][is_correct]" id="optCorrect\${optionCount}" value="1">
                           <label class="custom-control-label" for="optCorrect\${optionCount}"><strong>Benar</strong></label>
                       </div>
                   </div>`
                : `<input type="hidden" name="options[\${optionCount}][is_correct]" value="0">`;
            div.innerHTML = `
                \${correctBlock}
                <input type="text" class="form-control" name="options[\${optionCount}][text]" placeholder="Opsi \${optionCount + 1}" required maxlength="200">
                <div class="input-group-append">
                    <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(div);
            optionCount++;
        });
    }
</script>
@endsection