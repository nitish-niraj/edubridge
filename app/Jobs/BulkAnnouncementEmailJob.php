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

        $query = User::where('status', 'active');
        if ($targetRole !== 'all') {
            $query->where('role', $targetRole);
        }

        $batchIndex = 0;
        $sentCount = 0;
        $query->select('id')->chunk(100, function ($users) use (&$batchIndex, &$sentCount, $announcement): void {
            $ids = $users->pluck('id')->values()->all();
            $sentCount += count($ids);
            if (app()->environment('testing')) {
                SendAnnouncementEmailChunkJob::dispatchSync($announcement->id, $ids);
            } else {
                SendAnnouncementEmailChunkJob::dispatch($announcement->id, $ids)
                    ->delay(now()->addMilliseconds($batchIndex * 500));
            }
            $batchIndex++;
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
