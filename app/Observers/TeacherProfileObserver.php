<?php

namespace App\Observers;

use App\Models\TeacherProfile;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class TeacherProfileObserver
{
    public function saving(TeacherProfile $teacherProfile): void
    {
        $this->invalidateTeachersCache();
    }

    public function deleted(TeacherProfile $teacherProfile): void
    {
        $this->invalidateTeachersCache();
    }

    private function invalidateTeachersCache(): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['teachers'])->flush();
        }

        $version = (int) Cache::get('teachers:cache_version', 1);
        Cache::forever('teachers:cache_version', $version + 1);
    }
}
