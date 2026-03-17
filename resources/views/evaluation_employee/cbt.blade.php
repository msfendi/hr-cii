<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex justify-content-between mb-4">
              <h1 class="h3 text-gray-800">Employee Evaluation</h1>
              <h4 class="text-danger">Sisa Waktu: <span id="timer">30:00</span>
              </h4>
            </div>
            <form id="evaluation-employeeForm" method="POST" action="{{ route('evaluation-employee.submit') }}"> @csrf <input type="hidden" name="npk" value="{{ $npk }}"><input type="hidden" name="jobscope_id" value="{{ $jobscope_id }}">
              <div class="card shadow mb-4">
                <div class="card-body"> @foreach($questions as $index => $q) <div class="mb-4">
                    <p>
                      <strong>{{ $index+1 }}. {{ $q->question }}</strong>
                    </p>
                    <div>
                      <label>
                        <input type="radio" name="answers[{{ $q->id }}]" value="A"> {{ $q->optiona }}
                      </label>
                      <br>
                      <label>
                        <input type="radio" name="answers[{{ $q->id }}]" value="B"> {{ $q->optionb }}
                      </label>
                      <br>
                      <label>
                        <input type="radio" name="answers[{{ $q->id }}]" value="C"> {{ $q->optionc }}
                      </label>
                      <br>
                      <label>
                        <input type="radio" name="answers[{{ $q->id }}]" value="D"> {{ $q->optiond }}
                      </label>
                    </div>
                  </div>
                  <hr> @endforeach <button type="submit" class="btn btn-success">Submit Jawaban</button>
                </div>
              </div>
            </form>
          </div>
        </div> @include('layout.footer') <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
          // TIMER 30 MENIT
          let time = 30 * 60; // detik
          let timer = setInterval(function() {
            let minutes = Math.floor(time / 60);
            let seconds = time % 60;
            document.getElementById('timer').innerHTML = minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
            time--;
            if (time < 0) {
              clearInterval(timer);
              Swal.fire({
                title: 'Waktu Habis!',
                text: 'Jawaban akan otomatis dikirim',
                icon: 'warning',
                showConfirmButton: false,
                timer: 2000
              });
              setTimeout(() => {
                document.getElementById('evaluation-employeeForm').submit();
              }, 2000);
            }
          }, 1000);
        </script>
        <script>
          history.pushState(null, null, location.href);
          window.onpopstate = function () {
              history.go(1);
          };
        </script>
  </body>
</html>