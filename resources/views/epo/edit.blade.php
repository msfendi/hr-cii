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
                  <h1 class="h3 mb-4 text-gray-800">
                     Edit EPO
                  </h1>
                  <div class="card shadow">
                     <div class="card-body">
                        <form method="POST" action="{{ route('epo.update',$data->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="row">
                           <div class="col-md-6">
                              <div class="form-group">
                                 <label>Expat Name</label>
                                 <input type="text" name="expat_name"
                                    class="form-control"
                                    value="{{$data->expat_name}}">
                              </div>
                              <div class="form-group">
                                 <label>Gender</label>
                                 <select name="gender" class="form-control">
                                 <option {{$data->gender=='Male'?'selected':''}}>Male</option>
                                 <option {{$data->gender=='Female'?'selected':''}}>Female</option>
                                 </select>
                              </div>
                              <div class="form-group">
                                 <label>Place</label>
                                 <input type="text" name="place"
                                    class="form-control"
                                    value="{{$data->place}}">
                              </div>
                              <div class="form-group">
                                 <label>Birth Date</label>
                                 <input type="date" name="date_of_birth"
                                    class="form-control"
                                    value="{{ $data->date_of_birth ? $data->date_of_birth->format('Y-m-d') : '' }}">
                              </div>
                              <div class="form-group">
                                 <label>Nationality</label>
                                 <input type="text" name="nationality"
                                    class="form-control"
                                    value="{{$data->nationality}}">
                              </div>
                              <div class="form-group">
                                 <label>Position</label>
                                 <input type="text" name="position"
                                    class="form-control"
                                    value="{{$data->position}}">
                              </div>
                           </div>
                           <div class="col-md-6">
                              <div class="form-group">
                                 <label>Department</label>
                                 <input type="text" name="department"
                                    class="form-control"
                                    value="{{$data->department}}">
                              </div>
                              <div class="form-group">
                                 <label>Termination Date</label>
                                 <input type="date" name="termination_date"
                                    class="form-control"
                                    value="{{ $data->termination_date ? $data->termination_date->format('Y-m-d') : '' }}">
                              </div>
                              <div class="form-group">
                                 <label>Must Leave Indonesia</label>
                                 <input type="date" name="must_leave_date"
                                    class="form-control"
                                    value="{{ $data->must_leave_date ? $data->must_leave_date->format('Y-m-d') : '' }}">
                              </div>
                              <div class="form-group">
                                 <label>EPO Cost</label>
                                 <input type="number" name="epo_cost"
                                    class="form-control"
                                    value="{{$data->epo_cost}}">
                              </div>
                              <div class="form-group">
                                 <label>RPTKA Cancellation Cost</label>
                                 <input type="number" name="rptka_cancellation_cost"
                                    class="form-control"
                                    value="{{$data->rptka_cancellation_cost}}">
                              </div>
                              <div class="form-group">
                                 <label>Remarks</label>
                                 <input type="text" name="remarks"
                                    class="form-control"
                                    value="{{$data->remarks}}">
                              </div>
                           </div>
                        </div>
                        <button class="btn btn-success btn-block mt-4">
                        Update Data
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
</html>