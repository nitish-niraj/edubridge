<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$booking = App\Models\Booking::find(6);
$user = App\Models\User::find($booking->student_id);

$request = Illuminate\Http\Request::create('/api/video-sessions/6/token', 'POST');
$request->setUserResolver(function() use ($user) { return $user; });

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";
