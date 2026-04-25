<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementStoreRequest;
use App\Http\Resources\AnnouncementResource;
use App\Jobs\BulkAnnouncementEmailJob;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Announcement::with('creator:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return AnnouncementResource::collection($announcements)->response();
    }

    public function store(AnnouncementStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $announcement = Announcement::create([
            'title'         => $validated['title'],
            'message'       => $validated['message'],
            'target_role'   => $validated['target_role'],
            'delivery_type' => $validated['delivery_type'],
            'starts_at'     => $validated['starts_at'],
            'ends_at'       => $validated['ends_at'] ?? null,
            'created_by'    => auth()->id(),
        ]);

        if (in_array($validated['delivery_type'], ['email', 'both'], true)) {
            BulkAnnouncementEmailJob::dispatch($announcement->id);
        }

        return (new AnnouncementResource($announcement->load('creator:id,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(int $id): JsonResponse
    {
        Announcement::findOrFail($id)->delete();
        return response()->json(['message' => 'Announcement deleted.']);
    }

    /**
     * Active announcements for the current user's role (public endpoint).
     */
    public function active(Request $request): JsonResponse
    {
        $role = $request->user()->role ?? 'student';
        $announcements = Announcement::activeForRole($role)
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(AnnouncementResource::collection($announcements)->resolve());
    }
}
