<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 | Too Many Requests</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#fefce8; color:#713f12; font-family:Arial, sans-serif; }
        .card { width:min(560px, calc(100% - 32px)); background:#fff; border:1px solid #fde68a; border-radius:18px; padding:28px; text-align:center; }
        h1 { margin:0 0 10px; font-size:48px; }
        p { margin:0 0 18px; line-height:1.6; color:#854d0e; }
        a { display:inline-flex; min-height:42px; align-items:center; justify-content:center; padding:0 16px; border-radius:999px; background:#a16207; color:#fff; text-decoration:none; font-weight:700; }
    </style>
</head>
<body>
<div class="card">
    <h1>429</h1>
    <h2>Too Many Requests</h2>
    <p>You are making requests too quickly. Please wait a moment and try again.</p>
    <a href="{{ route('landing') }}">Back to home</a>
</div>
</body>
</html>
