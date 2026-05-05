<?php

namespace App\Jobs;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class BulkAnnouncementEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::findOrFail($this->announcementId);
        $targetRole = $announcement->target_role;
        $sentCount = 0;

        $query = User::where('status', 'active');
        if ($targetRole !== 'all') {
            $query->where('role', $targetRole);
        }

        $query->chunk(100, function ($users) use ($announcement, &$sentCount) {
            foreach ($users as $user) {
                Mail::to($user->email)->send(new AnnouncementMail($announcement));
                $sentCount++;
            }
            // 500ms delay between batches
            usleep(500000);
        });

        $announcement->update(['sent_count' => $sentCount]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('BulkAnnouncementEmailJob failed.', [
            'announcement_id' => $this->announcementId,
            'error' => $exception->getMessage(),
        ]);
    }
}
