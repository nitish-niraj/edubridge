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
use Illuminate\Support\Facades\Mail;

class SendAnnouncementEmailChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $announcementId,
        public array $recipientIds
    ) {}

    public function handle(): void
    {
        $announcement = Announcement::find($this->announcementId);
        if (! $announcement) {
            return;
        }

        User::query()
            ->whereIn('id', $this->recipientIds)
            ->where('status', 'active')
            ->get(['id', 'email'])
            ->each(function (User $user) use ($announcement): void {
                if ($user->email) {
                    Mail::to($user->email)->send(new AnnouncementMail($announcement));
                }
            });
    }
}
