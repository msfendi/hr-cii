<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Chutex E-Signature</title>

    <!-- Custom fonts for this template-->
    <link rel="icon" type="image/x-icon" href="{{asset('storage/images/signature_icon.ico')}}">
    <link href="{{asset('vendor/fontawesome-free/css/all.min.css')}}" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="{{asset('css/sb-admin-2.min.css')}}" rel="stylesheet">
    <link href="{{asset('css/select2.min.css')}}" rel="stylesheet">
    <!-- Custom styles for this page -->
    <link href="{{asset('vendor/datatables/dataTables.bootstrap4.min.css')}}" rel="stylesheet">   
    <!-- <link rel="stylesheet" type="text/css" href="http://keith-wood.name/css/jquery.signature.css"> -->
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
    <style>
        #canvas-container {
            position: relative;
            width: max-content;
            height: max-content;
            padding: 0px;
        }
        #parent{position:relative; overflow:scroll; width:auto; border:1px solid blue; }
        .draggable {
            display: none;
            width: 120px;
            height: 70px;
            background-color: rgba(255, 0, 0, 0.4);
            touch-action: none;
            user-select: none;
            text-align: center;
            color: white;
            position: absolute;
        }
        .draggable-2 {
            display: none;
            width: 240px;
            height: 150px;
            background-color: rgba(255, 0, 0, 0.4);
            touch-action: none;
            user-select: none;
            text-align: center;
            color: white;
            position: absolute;
        }
        #sig-canvas {
			border: 1px solid #CCCCCC;
			border-radius: 5px;
            height: 300px;
			cursor: crosshair;
		}
        #sig-canvas-2 {
			border: 1px solid #CCCCCC;
			border-radius: 5px;
            height: 300px;
			cursor: crosshair;
            background-image: url("{{ asset('/storage/signature/chutex_stamp.png') }}") 
		}

		#sig-dataUrl {
			width: 100%;
		}
    </style>  
    
    <style>
        .select2 {
            width:100%!important;
        }
        .container-center {
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            -ms-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
        }
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
        }

            /* Style the buttons that are used to open the tab content */
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
        }

            /* Change background color of buttons on hover */
        .tab button:hover {
            background-color: #ddd;
        }

            /* Create an active/current tablink class */
        .tab button.active {
            background-color: #ccc;
        }

            /* Style the tab content */
        .tabcontent {
            display: none;
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-top: none;
        }
    </style>
</head>