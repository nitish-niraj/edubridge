<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ExportUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $role,
        public string $adminEmail
    ) {}

    public function handle(): void
    {
        $filename = "exports/users-{$this->role}-" . now()->format('Ymd-His') . '.csv';

        $query = User::query();
        if ($this->role !== 'all') {
            $query->where('role', $this->role);
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Registered']);

        $query->chunk(500, function ($users) use ($csv) {
            foreach ($users as $user) {
                fputcsv($csv, [
                    $user->id, $user->name, $user->email, $user->phone,
                    $user->role, $user->status, $user->created_at->toDateString(),
                ]);
            }
        });

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        Storage::disk('local')->put($filename, $content);

        $downloadUrl = URL::temporarySignedRoute(
            'admin.exports.download',
            now()->addDays(2),
            ['file' => $filename]
        );

        Mail::raw("Your user export is ready: {$downloadUrl}", function ($msg) {
            $msg->to($this->adminEmail)->subject('EduBridge — User Export Ready');
        });
    }
}
