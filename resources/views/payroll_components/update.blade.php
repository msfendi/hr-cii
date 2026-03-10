<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
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
                    <h1 class="h3 mb-0 text-gray-800">Update Payroll Component</h1>
                </div>
                

                <!-- Approach -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Form Update Payroll Component</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('payroll-components.update') }}" enctype="multipart/form-data">
                            @csrf
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
                            <div>
                                <input class="form-control" type="hidden" id="id" name="id" value="{{ $components->id }}" readonly>
                            </div>
                            
                            <div>
                                <label>Name :</label>
                                <input class="form-control" type="text" id="name" name="name" value="{{ $components->name}}">
                            </div>
                            <br>
                            <div>
                                <label>Code :</label>
                                <input class="form-control" type="text" id="code" name="code" value="{{ $components->code}}">
                            </div>
                            <br>
                            <div>
                                <label>Type :</label>
                                <select class="form-control" id="type" name="type">
                                    <option value="earning">Earning</option>
                                    <option value="deduction">Deduction</option>
                                </select>
                            </div>
                            <br>
                            <div>
                                <label>Calculation Method :</label>
                                <select class="form-control" id="calculation_method" name="calculation_method">
                                    <option value="fixed">Fixed</option>
                                    <option value="formula">Formula</option>
                                </select>
                            </div>
                            <br>
                            <div>
                                <label>Value :</label>
                                <input class="form-control" type="text" id="value" name="value" value="{{ $components->value}}">
                            </div>
                            <br>
                            <div>
                                <label>Formula :</label>
                                <input class="form-control" type="text" id="formula" name="formula" value="{{ $components->formula}}">
                            </div>
                            <br>
                            <div>
                                <label>Description :</label>
                                <input class="form-control" type="text" id="description" name="description" value="{{ $components->description}}">
                            </div>
                            <br>
                            <div>
                                <label>Category :</label>
                                <input class="form-control" type="text" id="category" name="category" value="{{ $components->category}}">
                            </div>
                            <br>
                            <div>
                                <label>Priority :</label>
                                <input class="form-control" type="number" id="priority" name="priority" value="{{ $components->priority}}">
                            </div>
                            <br>
                            <div>
                                <label>Is Taxable :</label>
                                <select class="form-control" id="is_taxable" name="is_taxable">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <br>
                            <div>
                                <label>Is Active :</label>
                                <select class="form-control" id="is_active" name="is_active">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                            <br>
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-block">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Content Row -->

            </div>
            <!-- /.container-fluid -->

        </div>
        <!-- End of Main Content -->

@include('layout.footer')
</body>
<script>
    $("#category_no").select2({
          allowClear: true,
          placeholder: 'Choose Category',
    });
</script>
</html>