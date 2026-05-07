<?php

namespace App\Console\Commands;

use App\Events\GroupSessionEnded;
use App\Models\VideoSession;
use Illuminate\Console\Command;

class CloseInactiveGroupSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:close-inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-close group sessions inactive for 10+ minutes (teacher dropped)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = now()->subMinutes(10);

        $inactiveSessions = VideoSession::query()
            ->where('is_group', true)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->where('updated_at', '<', $threshold)
            ->get();

        $closed = 0;

        foreach ($inactiveSessions as $session) {
            $duration = $session->started_at
                ? (int) $session->started_at->diffInMinutes(now())
                : 0;

            $session->update([
                'ended_at' => now(),
                'duration_minutes' => $duration,
            ]);

            if ($session->conversation_id) {
                broadcast(new GroupSessionEnded((int) $session->conversation_id, $session->id));
            }

            $closed++;
        }

        if ($closed > 0) {
            $this->info("Closed {$closed} inactive group session(s).");
        } else {
            $this->info('No inactive sessions found.');
        }

        return Command::SUCCESS;
    }
}
