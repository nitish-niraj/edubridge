<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 | Session Expired</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#fff7ed; color:#7c2d12; font-family:Arial, sans-serif; }
        .card { width:min(560px, calc(100% - 32px)); background:#fff; border:1px solid #fed7aa; border-radius:18px; padding:28px; text-align:center; }
        h1 { margin:0 0 10px; font-size:48px; }
        p { margin:0 0 18px; line-height:1.6; color:#9a3412; }
        a { display:inline-flex; min-height:42px; align-items:center; justify-content:center; padding:0 16px; border-radius:999px; background:#ea580c; color:#fff; text-decoration:none; font-weight:700; }
    </style>
</head>
<body>
<div class="card">
    <h1>419</h1>
    <h2>Session Expired</h2>
    <p>Your session has expired. Please refresh the page and try again.</p>
    <a href="{{ url()->current() }}">Refresh page</a>
</div>
</body>
</html>
