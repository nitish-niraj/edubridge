<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewIndexRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(ReviewIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = Review::with(['reviewer:id,name,avatar', 'reviewee:id,name,avatar']);

        if ($validated['flagged_only'] ?? false) {
            $query->where('is_flagged', true);
        }

        $reviews = $query->orderByDesc('created_at')->paginate(20);

        return ReviewResource::collection($reviews)->response();
    }

    public function toggleVisibility(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update(['is_visible' => ! $review->is_visible]);

        return response()->json([
            'is_visible' => (bool) $review->is_visible,
            'review' => (new ReviewResource($review->load(['reviewer:id,name,avatar', 'reviewee:id,name,avatar'])))->resolve(),
        ]);
    }
}
