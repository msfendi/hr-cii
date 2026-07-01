<!DOCTYPE html>
<html lang="en"> @include('layout.header') <body id="page-top"> @include('sweetalert::alert') <div id="wrapper"> @include('layout.sidebar') <div id="content-wrapper" class="d-flex flex-column">
        <div id="content"> @include('layout.navbar') <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
              <h1 class="h3 mb-0 text-gray-800">Whatsapp Contacts</h1>
              @canRoute('applicant-contact.create')
              <a href="{{ route('applicant-contact.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Contact </a>
              @endcanRoute
            </div>
            <div class="card shadow mb-4">
              <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"> Contact List </h6>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-bordered table-sm" id="dataTable">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Send Message</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody> @foreach($applicant_contact as $contact) <tr>
                        <td>{{ $contact->id }}</td>
                        <td>{{ $contact->name }}</td>
                        <td>{{ $contact->phone }}</td>
                        <td>{{ $contact->position }}</td>
                        <!-- SEND MESSAGE -->
                        <td class="text-center"> @foreach($templates as $template) @php $alreadySent = isset($sentLogs[$contact->phone]) && $sentLogs[$contact->phone] ->where('template_id',$template->id) ->count(); @endphp @if(!$alreadySent)
                          
                          @canRoute('applicant-contact.send')
                          <a href="{{ route('applicant-contact.send',['contact_id'=>$contact->id,'template_id'=>$template->id]) }}" class="btn btn-success btn-sm mb-1">
                            {{ $template->name }}
                          </a> @endif @endforeach 
                          @endcanRoute</td>
                        <!-- ACTION -->
                        <td class="text-center">
                          @canRoute('applicant-contact.destroy')
                          <form method="POST" action="{{ route('applicant-contact.destroy',$contact->id) }}"> @csrf @method('DELETE') <button class="btn btn-danger btn-circle btn-sm">
                              <i class="fas fa-trash"></i>
                            </button>
                          </form>
                          @endcanRoute
                        </td>
                      </tr> @endforeach </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div> @include('layout.footer')
      </div>
    </div>
  </body>
  <script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
  <script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
  <script src="{{asset('js/demo/datatables-demo.js')}}"></script>
</html>