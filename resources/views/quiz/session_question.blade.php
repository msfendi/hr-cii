@extends('layouts.app')

@section('title', 'Pertanyaan - ' . $quiz->title)

@section('content')
<div class="container">
    <div class="row justify-content-center mt-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $quiz->title }}</h5>
                        <span class="badge badge-light text-dark">{{ $session->participant_name }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Progress -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="font-weight-bold">Pertanyaan <span id="currentQNum">{{ $question->sort_order + 1 }}</span> dari {{ max(1, (int)$quiz->questions_count) }}</small>
                            <small class="text-muted">
                                @if($question->type === 'open_text')
                                    Jawaban Bebas
                                @elseif($question->type === 'multiple_choice')
                                    Pilihan Ganda
                                @else
                                    Pilihan Tunggal
                                @endif
                            </small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: {{ (($question->sort_order + 1) / max(1, (int)$quiz->questions_count)) * 100 }}%;" 
                                 aria-valuenow="{{ $question->sort_order + 1 }}" aria-valuemin="0" aria-valuemax="{{ max(1, (int)$quiz->questions_count) }}"></div>
                        </div>
                    </div>

                    <!-- Question -->
                    <div class="mb-4">
                        <h5 class="font-weight-bold text-gray-800">{{ $question->question_text }}</h5>
                    </div>

                    <!-- Answer Area -->
                    <form id="answerForm">
                        @csrf
                        <input type="hidden" name="question_id" value="{{ $question->id }}">
                        <input type="hidden" name="session_id" value="{{ $session->id }}">

                        @if($question->type === 'open_text')
                            {{-- ===== WORD CLOUD: free-text input ===== --}}
                            @if($existing)
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-2"></i> Anda sudah menjawab pertanyaan ini.
                                    <br><strong>Jawaban Anda:</strong> "{{ $existing->answer_text }}"
                                </div>
                                <textarea class="form-control mb-3" rows="3" disabled>{{ $existing->answer_text }}</textarea>
                                <div class="mt-4">
                                    @if($nextQuestion)
                                        <a href="{{ url('/quiz/session/' . $session->id . '/question/' . $nextQuestion->id) }}" class="btn btn-primary btn-lg btn-block">
                                            <i class="fas fa-arrow-right mr-2"></i> Pertanyaan Selanjutnya
                                        </a>
                                    @else
                                        <a href="{{ url('/quiz/session/' . $session->id . '/result') }}" class="btn btn-success btn-lg btn-block">
                                            <i class="fas fa-flag-checkered mr-2"></i> Selesai
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="form-group">
                                    <label for="answer_text" class="font-weight-bold">Ketik jawaban Anda</label>
                                    <textarea name="answer_text" id="answer_text" class="form-control" rows="3" required maxlength="500" placeholder="Satu atau beberapa kata..."></textarea>
                                    <small class="form-text text-muted">Maksimal 500 karakter. Kata-kata akan digabungkan ke awan kata.</small>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg btn-block mt-3">
                                    <i class="fas fa-paper-plane mr-2"></i> Kirim Jawaban
                                </button>
                            @endif

                        @else
                            {{-- ===== QUIZ / POLLING: choice-based ===== --}}
                            @if($existing)
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-2"></i> Anda sudah menjawab pertanyaan ini.
                                    <strong>Jawaban Anda:</strong> {{ $existing->option ? $existing->option->option_text : '(teks)' }}
                                </div>
                                @foreach($question->options as $opt)
                                    <div class="custom-control custom-{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }} mb-3">
                                        <input type="{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }}" 
                                               class="custom-control-input" 
                                               id="opt_{{ $opt->id }}" 
                                               name="option_id" 
                                               value="{{ $opt->id }}"
                                               disabled
                                               {{ $existing->option_id == $opt->id ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold {{ $existing->option_id == $opt->id ? 'text-primary' : '' }}" for="opt_{{ $opt->id }}">
                                            {{ $opt->option_text }}
                                        </label>
                                    </div>
                                @endforeach
                                <div class="mt-4">
                                    @if($nextQuestion)
                                        <a href="{{ url('/quiz/session/' . $session->id . '/question/' . $nextQuestion->id) }}" class="btn btn-primary btn-lg btn-block">
                                            <i class="fas fa-arrow-right mr-2"></i> Pertanyaan Selanjutnya
                                        </a>
                                    @else
                                        <a href="{{ url('/quiz/session/' . $session->id . '/result') }}" class="btn btn-success btn-lg btn-block">
                                            <i class="fas fa-flag-checkered mr-2"></i> Lihat Hasil
                                        </a>
                                    @endif
                                </div>
                            @else
                                @foreach($question->options as $opt)
                                    <div class="custom-control custom-{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }} mb-3">
                                        <input type="{{ $question->type === 'multiple_choice' ? 'checkbox' : 'radio' }}" 
                                               class="custom-control-input" 
                                               id="opt_{{ $opt->id }}" 
                                               name="option_id" 
                                               value="{{ $opt->id }}"
                                               required>
                                        <label class="custom-control-label" for="opt_{{ $opt->id }}">
                                            {{ $opt->option_text }}
                                        </label>
                                    </div>
                                @endforeach
                                <button type="submit" class="btn btn-primary btn-lg btn-block mt-3">
                                    <i class="fas fa-paper-plane mr-2"></i> Kirim Jawaban
                                </button>
                            @endif
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('answerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        if (!btn) return; // already answered → no submit button
        const formData = new FormData(this);
        const isChoice = {{ $question->type !== 'open_text' ? 'true' : 'false' }};
        const data = {};

        if (isChoice) {
            data.question_id = formData.get('question_id');
            if ({{ $question->type === 'multiple_choice' ? 'true' : 'false' }}) {
                data.option_ids = formData.getAll('option_id');
                if (data.option_ids.length === 0) { alert('Pilih minimal satu jawaban.'); return; }
            } else {
                data.option_id = formData.get('option_id');
                if (!data.option_id) { alert('Pilih satu jawaban.'); return; }
            }
        } else {
            // open_text (word cloud)
            data.question_id = formData.get('question_id');
            data.answer_text = formData.get('answer_text') || '';
            if (!data.answer_text.trim()) { alert('Tulis jawaban terlebih dahulu.'); return; }
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Mengirim...';

        try {
            const res = await fetch('{{ url("/quiz/session/" . $session->id . "/answer") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (result.success) {
                if (result.next_question_id) {
                    window.location.href = '{{ url("/quiz/session/" . $session->id . "/question") }}' + '/' + result.next_question_id;
                } else {
                    window.location.href = '{{ url("/quiz/session/" . $session->id . "/result") }}';
                }
            } else {
                alert(result.message || 'Gagal mengirim jawaban.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Kirim Jawaban';
            }
        } catch (err) {
            console.error(err);
            alert('Gagal terhubung ke server.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Kirim Jawaban';
        }
    });
</script>
@endsection