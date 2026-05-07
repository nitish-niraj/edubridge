<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted {--days=90 : Days to retain soft-deleted users}';

    protected $description = 'Permanently delete users soft-deleted beyond retention window.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $count = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $user->forceDelete();
                }
            });

        $this->info('Purge completed.');

        return self::SUCCESS;
    }
}

