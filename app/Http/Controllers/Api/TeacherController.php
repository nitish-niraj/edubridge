<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TeacherIndexRequest;
use App\Http\Requests\Api\TeacherSearchRequest;
use App\Http\Requests\Api\TeacherShowRequest;
use App\Http\Resources\TeacherCardResource;
use App\Http\Resources\TeacherPublicProfileResource;
use App\Models\Review;
use App\Models\SavedTeacher;
use App\Models\TeacherProfile;
use Illuminate\Cache\TaggableStore;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TeacherController extends Controller
{
    public function index(TeacherIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 12);

        $cacheKey = 'teachers:index:' . md5(json_encode([
            'viewer_id' => $request->user()?->id,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => $perPage,
            'filters' => $validated,
        ]));

        try {
            $teachers = $this->rememberTeacherResults($cacheKey, function () use ($request, $validated, $perPage) {
                $query = $this->baseTeacherQuery($request->user()?->id);
                $this->applyFilters($query, $validated);
                $this->applySort($query, $validated['sort'] ?? 'rating_desc');

                return $query->paginate($perPage)->withQueryString();
            });
        } catch (QueryException) {
            throw new ServiceUnavailableHttpException(null, 'Teacher directory is temporarily unavailable. Please try again soon.');
        }

        return TeacherCardResource::collection($teachers);
    }

    public function search(TeacherSearchRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 12);
        $sort = $validated['sort'] ?? 'relevance';

        try {
            try {
                $scoutIds = TeacherProfile::search($validated['q'])->keys()->map(fn ($id): int => (int) $id);
            } catch (\Throwable) {
                $scoutIds = collect();
            }

            $query = $this->baseTeacherQuery($request->user()?->id)
                ->when($scoutIds->isNotEmpty(), fn ($builder) => $builder->whereIn('teacher_profiles.id', $scoutIds->all()));

            if ($scoutIds->isEmpty()) {
                $this->applySearchFallback($query, (string) $validated['q']);
            }

            $this->applyFilters($query, $validated);

            if ($sort === 'relevance') {
                if ($scoutIds->isNotEmpty()) {
                    $orderedIds = $scoutIds->values()->all();
                    $driver = $query->getQuery()->getConnection()->getDriverName();

                    if ($driver === 'mysql') {
                        $query->orderByRaw('FIELD(teacher_profiles.id, ' . implode(',', $orderedIds) . ')');
                    } else {
                        $caseParts = [];
                        foreach ($orderedIds as $index => $id) {
                            $caseParts[] = "WHEN {$id} THEN {$index}";
                        }
                        $query->orderByRaw('CASE teacher_profiles.id ' . implode(' ', $caseParts) . ' ELSE ' . count($orderedIds) . ' END');
                    }
                } else {
                    $query->orderByDesc('rating_avg')->orderByDesc('total_reviews');
                }
            } else {
                $this->applySort($query, $sort);
            }

            $teachers = $query->paginate($perPage)->withQueryString();
        } catch (QueryException) {
            throw new ServiceUnavailableHttpException(null, 'Teacher search is temporarily unavailable. Please try again soon.');
        }

        return TeacherCardResource::collection($teachers);
    }

    private function applySearchFallback(Builder $query, string $term): void
    {
        $keyword = trim($term);
        if ($keyword === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($keyword): void {
            $builder->whereHas('user', function (Builder $userQuery) use ($keyword): void {
                $userQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            })
                ->orWhere('bio', 'like', "%{$keyword}%")
                ->orWhere('subjects', 'like', '%"' . $keyword . '"%')
                ->orWhere('subjects', 'like', "%{$keyword}%")
                ->orWhere('languages', 'like', '%"' . $keyword . '"%')
                ->orWhere('languages', 'like', "%{$keyword}%");
        });
    }

    public function show(TeacherShowRequest $request, int $teacher): TeacherPublicProfileResource
    {
        $request->validated();
        $studentId = $request->user()?->isStudent() ? $request->user()->id : null;

        $query = TeacherProfile::query()
            ->with('user:id,name,avatar,status')
            ->where('user_id', $teacher)
            ->where('is_verified', true)
            ->whereHas('user', function (Builder $builder): void {
                $builder->where('role', 'teacher')->where('status', 'active');
            });

        if ($studentId) {
            $query->addSelect([
                'is_saved' => SavedTeacher::query()
                    ->selectRaw('count(*) > 0')
                    ->whereColumn('saved_teachers.teacher_id', 'teacher_profiles.user_id')
                    ->where('saved_teachers.student_id', $studentId),
            ]);
        }

        try {
            $profile = $query->first();
        } catch (QueryException) {
            throw new ServiceUnavailableHttpException(null, 'Teacher profile is temporarily unavailable. Please try again soon.');
        }

        if (! $profile) {
            throw new NotFoundHttpException('Teacher profile not found.');
        }

        $latestReviews = Review::query()
            ->where('reviewee_id', $teacher)
            ->where('is_visible', true)
            ->whereNotNull('comment')
            ->with(['reviewer:id,name'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(function (Review $review): array {
                return [
                    'id' => $review->id,
                    'rating' => (float) $review->rating,
                    'student_name' => $review->reviewer?->name,
                    'comment' => $review->comment,
                    'date' => optional($review->created_at)->toDateString(),
                ];
            })
            ->values()
            ->all();

        $profile->setAttribute('latest_reviews', $latestReviews);

        return new TeacherPublicProfileResource($profile);
    }

    private function baseTeacherQuery(?int $studentId = null): Builder
    {
        $query = TeacherProfile::query()
            ->select('teacher_profiles.*')
            ->with('user:id,name,avatar,status')
            ->where('is_verified', true)
            ->whereHas('user', function (Builder $builder): void {
                $builder->where('role', 'teacher')->where('status', 'active');
            });

        if ($studentId) {
            $query->addSelect([
                'is_saved' => SavedTeacher::query()
                    ->selectRaw('count(*) > 0')
                    ->whereColumn('saved_teachers.teacher_id', 'teacher_profiles.user_id')
                    ->where('saved_teachers.student_id', $studentId),
            ]);
        }

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $subjects = Arr::wrap($filters['subjects'] ?? []);
        if ($subjects !== []) {
            $query->where(function (Builder $builder) use ($subjects): void {
                foreach ($subjects as $subject) {
                    $builder->orWhereJsonContains('subjects', $subject);
                }
            });
        }

        $languages = Arr::wrap($filters['languages'] ?? []);
        if ($languages !== []) {
            $query->where(function (Builder $builder) use ($languages): void {
                foreach ($languages as $language) {
                    $builder->orWhereJsonContains('languages', $language);
                }
            });
        }

        $price = $filters['price'] ?? 'any';
        if ($price === 'free') {
            $query->where('is_free', true);
        } elseif ($price === 'under_200') {
            $query->where('is_free', false)->where('hourly_rate', '<', 200);
        } elseif ($price === '200_500') {
            $query->where('is_free', false)->whereBetween('hourly_rate', [200, 500]);
        } elseif ($price === '500_plus') {
            $query->where('is_free', false)->where('hourly_rate', '>=', 500);
        }

        if (isset($filters['min_rating'])) {
            $query->havingRaw('rating_avg >= ?', [(float) $filters['min_rating']]);
        }

        $gender = $filters['gender'] ?? 'any';
        if ($gender !== 'any') {
            $query->where('gender', $gender);
        }

        $days = Arr::wrap($filters['availability_days'] ?? []);
        if ($days !== []) {
            $query->where(function (Builder $builder) use ($days): void {
                foreach ($days as $day) {
                    foreach ($this->availabilityDayKeys($day) as $key) {
                        $path = '$."' . $key . '".enabled';
                        $builder->orWhereRaw('JSON_EXTRACT(availability, ?) = true', [$path]);
                        $builder->orWhereRaw('JSON_EXTRACT(availability, ?) = 1', [$path]);
                        $pathOn = '$."' . $key . '".on';
                        $builder->orWhereRaw('JSON_EXTRACT(availability, ?) = true', [$pathOn]);
                        $builder->orWhereRaw('JSON_EXTRACT(availability, ?) = 1', [$pathOn]);
                    }
                }
            });
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'price_asc') {
            $query->orderByRaw('CASE WHEN is_free = 1 THEN 0 ELSE hourly_rate END ASC');

            return;
        }

        if ($sort === 'price_desc') {
            $query->orderByRaw('CASE WHEN is_free = 1 THEN 0 ELSE hourly_rate END DESC');

            return;
        }

        if ($sort === 'experienced') {
            $query->orderByDesc('experience_years')->orderByDesc('rating_avg');

            return;
        }

        if ($sort === 'newest') {
            $query->orderByDesc('created_at');

            return;
        }

        $query->orderByDesc('rating_avg')->orderByDesc('total_reviews');
    }

    private function availabilityDayKeys(string $inputDay): array
    {
        $normalized = strtolower(substr($inputDay, 0, 3));

        return match ($normalized) {
            'mon' => ['mon', 'Mon', 'Monday'],
            'tue' => ['tue', 'Tue', 'Tuesday'],
            'wed' => ['wed', 'Wed', 'Wednesday'],
            'thu' => ['thu', 'Thu', 'Thursday'],
            'fri' => ['fri', 'Fri', 'Friday'],
            'sat' => ['sat', 'Sat', 'Saturday'],
            'sun' => ['sun', 'Sun', 'Sunday'],
            default => [$inputDay],
        };
    }

    private function rememberTeacherResults(string $cacheKey, \Closure $callback)
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags(['teachers'])->remember($cacheKey, 120, $callback);
        }

        return Cache::remember($cacheKey, 120, $callback);
    }
}
