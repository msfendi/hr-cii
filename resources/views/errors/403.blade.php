<!DOCTYPE html>
<html lang="en">
   <head>
      <meta charset="UTF-8">
      <title>403 - Access Denied</title>
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
         .btn{
         margin-top:25px;
         display:inline-block;
         padding:12px 25px;
         border-radius:50px;
         background:white;
         color:#0d6efd;
         text-decoration:none;
         font-weight:600;
         transition:.3s;
         }
         .btn:hover{
         transform:translateY(-2px);
         box-shadow:0 5px 15px rgba(0,0,0,.2);
         }
      </style>
   </head>
   <body>
      <div class="container">
         <div class="icon">🚫</div>
         <h1>403 - Access Denied</h1>
         <p>
            You do not have permission to access this page.<br>
            Your user role is not authorized for this route.
         </p>
         <div class="status">
            User does not have the required role
         </div>
         <a href="{{ url('/') }}" class="btn">
         Back to Dashboard
         </a>
         <div class="footer">
            © {{ date('Y') }} PT. Chutex International Indonesia
         </div>
      </div>
   </body>
</html>