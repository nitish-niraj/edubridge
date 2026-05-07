<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');
// Set the application instance
Illuminate\Support\Facades\Facade::setFacadeApplication($app);

use Illuminate\Support\Facades\DB;

// Check verified teachers
$verifiedCount = DB::table('teacher_profiles')->where('is_verified', true)->count();
echo "Verified teachers: $verifiedCount\n";

// Check active teacher users
$activeCount = DB::table('users')->where('role', 'teacher')->where('status', 'active')->count();
echo "Active teacher users: $activeCount\n";

// Get sample teachers
$teachers = DB::table('teacher_profiles')
    ->where('is_verified', true)
    ->select(['id', 'user_id', 'is_verified', 'subjects', 'languages', 'hourly_rate', 'rating_avg'])
    ->limit(3)
    ->get();

echo "\nSample teachers:\n";
foreach ($teachers as $t) {
    echo "ID: {$t->id}, User: {$t->user_id}, Verified: {$t->is_verified}, Subjects: " . substr($t->subjects, 0, 30) . "...\n";
}

// Check if there are any teacher profiles at all
$allTeachers = DB::table('teacher_profiles')->count();
echo "\nTotal teacher profiles: $allTeachers\n";

// Check base query
$query = DB::table('teacher_profiles')
    ->select('teacher_profiles.*')
    ->join('users', 'teacher_profiles.user_id', '=', 'users.id')
    ->where('teacher_profiles.is_verified', true)
    ->where('users.role', 'teacher')
    ->where('users.status', 'active')
    ->whereNotNull('users.avatar');

echo "Query result count: " . $query->count() . "\n";
echo "First teacher:\n";
$first = $query->first();
if ($first) {
    echo json_encode($first, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No teachers found\n";
}
