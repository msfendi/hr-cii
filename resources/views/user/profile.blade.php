<!DOCTYPE html>
<html lang="en">
@include('layout.header')
<body id="page-top">
<!-- Page Wrapper -->
@include('sweetalert::alert')
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
                    <h1 class="h3 mb-0 text-gray-800">Profile</h1>
                </div>
                
                <!-- DataTales Example -->
                <form method="POST" action="{{ route('signature.store') }}">
                @csrf
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Data</h6>
                                </div>
                                <div class="card-body">
                                        <input class="form-control" type="hidden" id="user_id" name="user_id" required value="{{ $user[0]->id }}">
										<input class="form-control" type="hidden" id="old_signature" name="old_signature" readonly value="{{ $user[0]->signature_img }}">
                                        <div>
                                            <label>Name :</label>
                                            <input class="form-control" type="text" id="name" name="name" readonly value="{{ $user[0]->name }}">
                                        </div>
                                        <br>
                                        <div>
                                            <label>Email :</label>
                                            <input class="form-control" type="text" id="email" name="email" readonly value="{{ $user[0]->email }}">
                                        </div>
                                        <br>
                                        <div>
                                            <label>Signature Image :</label>
                                            <br>
                                            <input class="form-control" type="hidden" id="signature_img" name="signature_img" readonly value="{{ $user[0]->signature_img }}">
                                            <img src="{{ asset('/storage/signature/' . $user[0]->signature_img) }}" width="100%">
                                        </div>
                                        <br>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Draw Signature</h6>
                                </div>
                                <div class="card-body">
                                    <canvas id="sig-canvas"></canvas>
                                    <canvas id="sig-canvas-2"></canvas>
                                    <textarea id="sig-dataUrl" name="signed" class="form-control" style="display: none;"></textarea>
                                    <br>
                                    <br>
                                    <button id="clear" class="btn btn-danger btn-block">Clear Signature</button>
                                    <button id="save" class="btn btn-success btn-block">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>

        <!-- Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md" role="document" >
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="delete-title" class="modal-title" id="exampleModalLabel">Delete Record</h5>
                        <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">x</span>
                        </button>
                    </div>
                    <div class="modal-body"><p id="modal-text-record"></p></div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" type="button" data-dismiss="modal">Tutup</button>
                        <a id="btn-confirm" href=""><button class="btn btn-primary" type="button">Confirm</button></a>
                    </div>
                </div>
            </div>
        </div>


@include('layout.footer')
</body>
<!-- Page level plugins -->
<script src="{{asset('vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>

<!-- Page level custom scripts -->
<script src="{{asset('js/demo/datatables-demo.js')}}"></script>
<script src="{{asset('vendor/jquery/jquery-ui.min.js')}}"></script>

<script src="{{asset('vendor/jquery/signature.js')}}"></script>
<script>
    (function() {
        // Get a regular interval for drawing to the screen
	window.requestAnimFrame = (function (callback) {
		return window.requestAnimationFrame || 
					window.webkitRequestAnimationFrame ||
					window.mozRequestAnimationFrame ||
					window.oRequestAnimationFrame ||
					window.msRequestAnimaitonFrame ||
					function (callback) {
					 	window.setTimeout(callback, 1000/60);
					};
	})();
	// Set up the canvas
	var canvas = document.getElementById("sig-canvas-2");
    canvas.style.width = "100%";
    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
	var ctx = canvas.getContext("2d");
	ctx.strokeStyle = "#222222";
	ctx.lineWith = 2;

	// Set up the UI
	var sigText = document.getElementById("sig-dataUrl");
	var clearBtn = document.getElementById("clear");
	var submitBtn = document.getElementById("save");
	clearBtn.addEventListener("click", function (e) {
        e.preventDefault();
		clearCanvas();
		sigText.innerHTML = "Data URL for your signature will go here!";
	}, false);
	submitBtn.addEventListener("click", function (e) {
		var dataUrl = canvas.toDataURL();
		sigText.innerHTML = dataUrl;
	}, false);

	// Set up mouse events for drawing
	var drawing = false;
	var mousePos = { x:0, y:0 };
	var lastPos = mousePos;
	canvas.addEventListener("mousedown", function (e) {
		drawing = true;
		lastPos = getMousePos(canvas, e);
	}, false);
	canvas.addEventListener("mouseup", function (e) {
		drawing = false;
	}, false);
	canvas.addEventListener("mousemove", function (e) {
		mousePos = getMousePos(canvas, e);
	}, false);

	// Set up touch events for mobile, etc
	canvas.addEventListener("touchstart", function (e) {
        if (e.target == canvas) {
            e.preventDefault();
        }
		mousePos = getTouchPos(canvas, e);
		var touch = e.touches[0];
		var mouseEvent = new MouseEvent("mousedown", {
			clientX: touch.clientX,
			clientY: touch.clientY
		});
		canvas.dispatchEvent(mouseEvent);
	}, false);
	canvas.addEventListener("touchend", function (e) {
        if (e.target == canvas) {
            e.preventDefault();
        }
		var mouseEvent = new MouseEvent("mouseup", {});
		canvas.dispatchEvent(mouseEvent);
	}, false);
	canvas.addEventListener("touchmove", function (e) {
        if (e.target == canvas) {
            e.preventDefault();
        }
		var touch = e.touches[0];
		var mouseEvent = new MouseEvent("mousemove", {
			clientX: touch.clientX,
			clientY: touch.clientY
		});
		canvas.dispatchEvent(mouseEvent);
	}, false);

	// Prevent scrolling when touching the canvas
	document.body.addEventListener("touchstart", function (e) {
		if (e.target == canvas) {
			e.preventDefault();
		}
	}, false);
	document.body.addEventListener("touchend", function (e) {
		if (e.target == canvas) {
			e.preventDefault();
		}
	}, false);
	document.body.addEventListener("touchmove", function (e) {
		if (e.target == canvas) {
			e.preventDefault();
		}
	}, false);

	// Get the position of the mouse relative to the canvas
	function getMousePos(canvasDom, mouseEvent) {
		var rect = canvasDom.getBoundingClientRect();
		return {
			x: mouseEvent.clientX - rect.left,
			y: mouseEvent.clientY - rect.top
		};
	}

	// Get the position of a touch relative to the canvas
	function getTouchPos(canvasDom, touchEvent) {
		var rect = canvasDom.getBoundingClientRect();
		return {
			x: touchEvent.touches[0].clientX - rect.left,
			y: touchEvent.touches[0].clientY - rect.top
		};
	}

	// Draw to the canvas
	function renderCanvas() {
		if (drawing) {
			ctx.moveTo(lastPos.x, lastPos.y);
			ctx.lineTo(mousePos.x, mousePos.y);
			ctx.stroke();
			lastPos = mousePos;
		}
	}

	function clearCanvas() {
		canvas.width = canvas.width;
	}

	// Allow for animation
	(function drawLoop () {
		requestAnimFrame(drawLoop);
		renderCanvas();
	})();

})();
</script>
</html>