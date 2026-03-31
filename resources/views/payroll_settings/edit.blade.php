<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<head>
    <style>
        /* Biar select2 sejajar dalam input-group */
        .input-group .select2-container {
            flex: 1 1 auto;
            width: 1% !important;
        }

        /* Samakan tinggi dengan input bootstrap */
        .select2-container .select2-selection--single {
            height: calc(2.25rem + 2px);
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
        }

        /* Align text */
        .select2-selection__rendered {
            line-height: 1.5 !important;
        }

        /* Fix arrow position */
        .select2-selection__arrow {
            height: 100% !important;
        }
    </style>
</head>
<body id="page-top">
<div id="wrapper">
@include('layout.sidebar')
<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">
        @include('layout.navbar')
        <div class="container-fluid">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Update Payroll Setting</h1>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Form Update Payroll Setting</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('payroll-setting.update', $setting->id) }}">
                        @csrf
                        @method('PUT')

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

                        <div class="form-group">
                            <label>Component:</label>
                            <input type="text" name="component" class="form-control" value="{{ $setting->component }}" readonly>
                        </div>
                        <br>

                        <div class="form-group">
                            <label>Approval Steps:</label>

                            <div id="approval-steps">

                            @php
                            $currentApproval = json_decode($setting->approval) ?? [];
                            @endphp

                            @foreach($currentApproval as $index => $npk)

                            @php
                            $emp = $employees[$npk] ?? null;
                            $display = $emp ? $npk.' - '.$emp->NAMA_KARYAWAN : $npk;
                            @endphp

                            <div class="input-group mb-2 step-row">

                                <div class="input-group-prepend">
                                    <span class="input-group-text">Step {{ $index+1 }}</span>
                                </div>

                                <!-- visible -->
                                <input type="text"
                                    class="form-control employee-search"
                                    list="employee-list"
                                    value="{{ $display }}"
                                    placeholder="Type employee..."
                                >

                                <!-- hidden (yang disimpan) -->
                                <input type="hidden"
                                    name="approval[]"
                                    class="employee-npk"
                                    value="{{ $npk }}"
                                >

                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger remove-step">Remove</button>
                                </div>

                            </div>

                            @endforeach
                            </div>

                            <button type="button" class="btn btn-secondary" id="add-step">
                            Add Step
                            </button>
                            </div>

                        <div class="row">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-block">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <datalist id="employee-list">
        @foreach($employees as $empNpk => $emp)
        <option value="{{ $empNpk }} - {{ $emp->NAMA_KARYAWAN }}"></option>
        @endforeach
    </datalist>
@include('layout.footer')

<!-- Select2 JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script>
$(document).ready(function(){

let employees = @json($employees);

// =============================
// Convert TEXT -> NPK
// =============================
$(document).on('input','.employee-search',function(){

    let text = $(this).val();
    let hidden = $(this).closest('.step-row').find('.employee-npk');

    let found = '';

    $.each(employees,function(npk,emp){

        let label = npk+' - '+emp.NAMA_KARYAWAN;

        if(label === text){
            found = npk;
            return false;
        }
    });

    hidden.val(found);
});


// =============================
// Create Step Row
// =============================
function createStepRow(stepNumber){

return $(`
<div class="input-group mb-2 step-row">

    <div class="input-group-prepend">
        <span class="input-group-text">Step ${stepNumber}</span>
    </div>

    <input type="text"
        class="form-control employee-search"
        list="employee-list"
        placeholder="Type employee..."
    >

    <input type="hidden"
        name="approval[]"
        class="employee-npk"
    >

    <div class="input-group-append">
        <button type="button" class="btn btn-danger remove-step">Remove</button>
    </div>

</div>
`);
}


// =============================
// ADD STEP
// =============================
$('#add-step').click(function(){

let stepNumber = $('#approval-steps .step-row').length + 1;

$('#approval-steps').append(createStepRow(stepNumber));

});


// =============================
// REMOVE STEP
// =============================
$(document).on('click','.remove-step',function(){

$(this).closest('.step-row').remove();

$('#approval-steps .step-row').each(function(i){
$(this).find('.input-group-text').text('Step '+(i+1));
});

});

});
</script>

</body>
</html>