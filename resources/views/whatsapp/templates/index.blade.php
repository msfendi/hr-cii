<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Whatsapp Templates</h1>
              <a href="{{ route('templates.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Template </a>
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Template List</h6>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Message</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody> @foreach($templates as $template) <tr>
                      <td>{{ $template->id }}</td>
                      <td>{{ $template->name }}</td>
                      <td>{{ $template->message }}</td>
                      <td class="text-center">
                        <form method="POST" action="{{ route('templates.destroy',$template->id) }}"> @csrf @method('DELETE') <button class="btn btn-danger btn-circle btn-sm">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr> @endforeach </tbody>
                </table>
                </div>
              </div>
            </div>
          </div>
        </div> @include('layout.footer') </body>
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
</html>