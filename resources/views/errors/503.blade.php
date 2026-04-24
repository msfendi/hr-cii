<!DOCTYPE html>

<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Mode</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">


<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">

<style>
    *{
        margin:0;
        padding:0;
        box-sizing:border-box;
        font-family:'Poppins',sans-serif;
    }

    body{
        height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg,#0d6efd,#4dabf7);
        color:white;
        text-align:center;
    }

    .container{
        background:rgba(255,255,255,0.08);
        backdrop-filter:blur(12px);
        padding:50px;
        border-radius:16px;
        box-shadow:0 10px 30px rgba(0,0,0,.2);
        max-width:500px;
        width:90%;
    }

    .icon{
        font-size:70px;
        margin-bottom:20px;
    }

    h1{
        font-size:32px;
        margin-bottom:10px;
        font-weight:700;
    }

    p{
        font-size:16px;
        opacity:.9;
        margin-bottom:30px;
    }

    .status{
        display:inline-block;
        padding:10px 20px;
        border-radius:50px;
        background:white;
        color:#0d6efd;
        font-weight:600;
    }

    .footer{
        margin-top:30px;
        font-size:13px;
        opacity:.8;
    }

    .loader{
        margin:25px auto;
        width:40px;
        height:40px;
        border:4px solid rgba(255,255,255,.3);
        border-top:4px solid white;
        border-radius:50%;
        animation:spin 1s linear infinite;
    }

    @keyframes spin{
        100%{transform:rotate(360deg);}
    }
</style>


</head>

<body>

<div class="container">


<div class="icon">🔧</div>

<h1>Sedang Maintenance</h1>

<p>
    Sistem sedang dilakukan peningkatan performa.<br>
    Silakan kembali beberapa saat lagi.
</p>

<div class="loader"></div>

<div class="status">
    Maintenance Mode Active
</div>

<div class="footer">
    © {{ date('Y') }} PT. Chutex International Indonesia
</div>


</div>

</body>
</html>
