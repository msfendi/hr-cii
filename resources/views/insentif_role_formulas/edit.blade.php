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
                  <h1 class="h3 mb-4 text-gray-800">
                     Update Insentif Role Formula
                  </h1>
                  <div class="card shadow mb-4">
                     <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                           Edit Formula
                        </h6>
                     </div>
                     <div class="card-body">
                        <form method="POST"
                           action="{{ route('insentif-role-formulas.update',$data->id) }}">
                           @csrf
                           @method('PUT')
                           {{-- ROLE --}}
                           <div class="form-group">
                              <label>Role</label>
                              <input type="text"
                                 name="role"
                                 class="form-control @error('role') is-invalid @enderror"
                                 value="{{ old('role',$data->role) }}"
                                 required>
                              @error('role')
                              <div class="invalid-feedback">
                                 {{ $message }}
                              </div>
                              @enderror
                           </div>
                           {{-- DEPT --}}
                           <div class="form-group">
                              <label>Department</label>

                              <select name="dept"
                                 id="dept"
                                 class="form-control @error('dept') is-invalid @enderror"
                                 required>

                                 <option value="">-- Select Department --</option>

                                 @foreach($depts as $dept)
                                       <option value="{{ $dept }}"
                                          {{ old('dept',$data->dept)==$dept ? 'selected':'' }}>
                                          {{ strtoupper($dept) }}
                                       </option>
                                 @endforeach

                              </select>

                              @error('dept')
                                 <div class="invalid-feedback">
                                       {{ $message }}
                                 </div>
                              @enderror
                           </div>
                           {{-- FORMULA --}}
                           <div class="form-group">
                              <label>Formula</label>
                              <textarea name="formula"
                                 rows="4"
                                 class="form-control @error('formula') is-invalid @enderror"
                                 required>{{ old('formula',$data->formula) }}</textarea>
                              @error('formula')
                              <div class="invalid-feedback">
                                 {{ $message }}
                              </div>
                              @enderror
                           </div>
                           <button class="btn btn-primary btn-block">
                           Update Formula
                           </button>
                        </form>
                     </div>
                  </div>
               </div>
            </div>
            @include('layout.footer')
         </div>
      </div>
   </body>
   <script>
$(document).ready(function() {

    $('#dept').select2({
        placeholder: 'Select Department',
        allowClear: true,
        width: '100%'
    });

});
</script>
</html>