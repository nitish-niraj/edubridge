<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 | Server Error</title>
    <style>
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:#eef2ff; color:#1e1b4b; font-family:Arial, sans-serif; }
        .card { width:min(560px, calc(100% - 32px)); background:#fff; border:1px solid #c7d2fe; border-radius:18px; padding:28px; text-align:center; }
        h1 { margin:0 0 10px; font-size:48px; }
        p { margin:0 0 18px; line-height:1.6; color:#3730a3; }
        a { display:inline-flex; min-height:42px; align-items:center; justify-content:center; padding:0 16px; border-radius:999px; background:#4338ca; color:#fff; text-decoration:none; font-weight:700; }
    </style>
</head>
<body>
<div class="card">
    <h1>500</h1>
    <h2>Something Went Wrong</h2>
    <p>Our team has been notified. Please try again in a few minutes.</p>
    <a href="{{ route('landing') }}">Return to home</a>
</div>
</body>
</html>
