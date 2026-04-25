<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Page Not Found</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#f8fbff; color:#102a43; font-family:Arial, sans-serif; }
        .card { width:min(560px, calc(100% - 32px)); background:#fff; border:1px solid #d9e6f2; border-radius:18px; padding:28px; text-align:center; }
        h1 { margin:0 0 10px; font-size:48px; }
        p { margin:0 0 18px; line-height:1.6; color:#486581; }
        a { display:inline-flex; min-height:42px; align-items:center; justify-content:center; padding:0 16px; border-radius:999px; background:#0d3b66; color:#fff; text-decoration:none; font-weight:700; }
    </style>
</head>
<body>
<div class="card">
    <h1>404</h1>
    <h2>Page Not Found</h2>
    <p>The page you are looking for does not exist or has been moved.</p>
    <a href="{{ route('landing') }}">Return to home</a>
</div>
</body>
</html>
