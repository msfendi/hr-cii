<!-- resources/views/food_order/employee.blade.php -->
<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
@include('sweetalert::alert')

@php
    // Mode "user" -> login via sistem HRIS biasa (ada sidebar & navbar).
    // Mode "qr"   -> masuk lewat scan QR (session food_order.npk), tanpa sidebar/navbar.
    $isQrMode = !auth()->check() && session()->has('food_order.npk');
@endphp

@if($isQrMode)
    {{-- ============== MODE QR: tanpa sidebar & navbar ============== --}}
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-fluid px-2 px-md-4">
                @include('food_order.partials.kiosk', ['isQrMode' => true])
            </div>
        </div>
        @include('layout.footer')
    </div>
@else
    {{-- ============== MODE USER: pakai layout HRIS normal ============== --}}
    <div id="wrapper">
        @include('layout.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layout.navbar')
                <div class="container-fluid px-2 px-md-4">
                    @include('food_order.partials.kiosk', ['isQrMode' => false])
                </div>
            </div>
            @include('layout.footer')
        </div>
    </div>
@endif

</body>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('orderDatePicker');
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            minDate: el.dataset.min,
            defaultDate: el.value,
            onChange: function () { el.form.submit(); }
        });
    });
</script>
</html>