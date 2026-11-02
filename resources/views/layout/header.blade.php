<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Chutex HRIS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

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
    
    @php
        // Vite: WAJIB pakai hasil build statis (public/build/manifest.json).
        //
        // PERBAIKAN: sebelumnya kalau file "public/hot" ada (mis. sempat
        // menjalankan `npm run dev` di server), header mengarahkan browser ke
        // dev server :5173. Di production dev server tidak jalan / ditolak
        // firewall -> browser dapat "403 Forbidden" untuk /@vite/client dan
        // /resources/js/app.js. Akibatnya SEMUA JS (jQuery, tab, dropdown)
        // mati => error "$ is not defined" dan tab tidak bisa diklik.
        //
        // Solusi: dev server hanya dipakai bila ENV VITE_DEV_SERVER=1
        // (untuk development lokal). Defaultnya SELALU build statis.
        $viteManifest = public_path('build/manifest.json');
        $viteForceDev = env('VITE_DEV_SERVER', false) === true
            || env('VITE_DEV_SERVER', '') === '1'
            || env('VITE_DEV_SERVER', '') === 'true';

        $viteHot = public_path('hot');
        $viteHasHot = file_exists($viteHot);
        $viteDevUrl = $viteHasHot ? trim((string) @file_get_contents($viteHot)) : '';

        $viteDevPort = 5173;
        $viteDevScheme = 'http';
        $viteDevHost = null;
        if ($viteDevUrl !== '') {
            $viteParts = parse_url($viteDevUrl);
            if (isset($viteParts['port'])) {
                $viteDevPort = (int) $viteParts['port'];
            }
            if (isset($viteParts['scheme'])) {
                $viteDevScheme = $viteParts['scheme'];
            }
            // Host dari request saat ini (mengikuti domain yang dibuka browser).
            $viteDevHost = request()->getHost();
        }
        // Dev server hanya aktif jika dipaksa lewat ENV DAN manifest build
        // tidak tersedia (dev memang belum pernah build).
        $viteUseDev = $viteForceDev
            && $viteHasHot
            && $viteDevUrl !== ''
            && $viteDevHost !== null
            && !file_exists($viteManifest);

        // Path client HMR. Sengaja dirangkai dari variabel: string "@vite"
        // literal di dalam Blade akan dibaca sebagai directive @vite oleh
        // Blade compiler dan menghasilkan error "Too few arguments to Vite::__invoke()".
        $viteClientPath = '@' . 'vite/client';
        $viteAppPath = 'resources/js/app.js';
    @endphp

    @if ($viteUseDev)
        {{-- Dev server aktif: bangun URL sendiri agar memakai domain request. --}}
        <script type="module" src="{{ $viteDevScheme }}://{{ $viteDevHost }}:{{ $viteDevPort }}/{{ $viteClientPath }}"></script>
        <script type="module" src="{{ $viteDevScheme }}://{{ $viteDevHost }}:{{ $viteDevPort }}/{{ $viteAppPath }}"></script>
    @elseif (file_exists($viteManifest))
        {{-- Build statis: jangan bergantung pada dev server sama sekali. --}}
        @php
            $viteManifestData = [];
            try {
                $viteManifestData = json_decode(@file_get_contents($viteManifest), true) ?: [];
            } catch (\Throwable $e) {
                $viteManifestData = [];
            }
        @endphp
        @foreach (['resources/css/app.css' => 'css', 'resources/js/app.js' => 'js'] as $viteEntry => $viteType)
            @php
                $viteChunk = $viteManifestData[$viteEntry] ?? null;
                $viteFile = $viteChunk['file'] ?? null;
                // Hanya emit stylesheet bila file benar-benar .css (hindari
                // manifest rusak yg menunjuk stub .js).
                $viteIsCss = $viteType === 'css'
                    && $viteFile
                    && str_ends_with(strtolower($viteFile), '.css')
                    && file_exists(public_path('build/' . $viteFile));
                $viteIsJs = $viteType === 'js'
                    && $viteFile
                    && str_ends_with(strtolower($viteFile), '.js')
                    && file_exists(public_path('build/' . $viteFile));
            @endphp
            @if ($viteIsCss)
                <link rel="stylesheet" href="{{ asset('build/' . $viteFile) }}">
            @elseif ($viteIsJs)
                <script type="module" src="{{ asset('build/' . $viteFile) }}"></script>
            @endif
        @endforeach
    @endif

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YTGQ4S3TRJ"></script>
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- DataTables (Bootstrap 5 skin) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.8/css/dataTables.bootstrap5.min.css">
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-YTGQ4S3TRJ');
    </script>
    
    <!-- <link rel="stylesheet" type="text/css" href="http://keith-wood.name/css/jquery.signature.css"> -->
    <!-- <script src="https://cdn.tailwindcss.com"></script> -->
     <style>
        @keyframes slideIn{
            from{
                transform:translateY(15px);
                opacity:0;
            }
            to{
                transform:translateY(0);
                opacity:1;
            }
        }
    </style>
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
    <style>

    #table-details{
        font-size:12px;
    }

    #table-details th{
        font-size:12px;
        white-space:nowrap;
    }

    #table-details td{
        font-size:12px;
        white-space:nowrap;
    }

    </style>
    <style>

    .file-input{
    height:31px;
    padding-top:3px;
    }

    .input-group-sm > .form-control,
    .input-group-sm > .input-group-append > .btn{
    height:31px;
    }

    </style>
    <style>
    #fileName{
        max-width:200px;
        overflow:hidden;
        white-space:nowrap;
        text-overflow:ellipsis;
    }
    </style>
    <style>

    .camera-box{
        width:100%;
        height:500px;
        background:#fff;
        border-radius:20px;
        overflow:hidden;
        display:flex;
        align-items:center;
        justify-content:center;
    }

    .camera-box-sm{
        width:100%;
        height:300px;
        background:#fff;
        border-radius:20px;
        overflow:hidden;
        display:flex;
        align-items:center;
        justify-content:center;
    }

    #video{
        width:100%;
        height:100%;
        object-fit:cover;
    }

    </style>
</head>
<script>
    window.reverbKey = "{{ config('broadcasting.connections.reverb.key') }}";
</script>