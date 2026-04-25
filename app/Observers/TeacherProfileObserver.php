<?php

namespace App\Observers;

use App\Models\TeacherProfile;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class TeacherProfileObserver
{
    public function saving(TeacherProfile $teacherProfile): void
    {
        $this->flushTeachersCache();
    }

    public function deleted(TeacherProfile $teacherProfile): void
    {
        $this->flushTeachersCache();
    }

    private function flushTeachersCache(): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['teachers'])->flush();
        }
    }
}
