<?php

namespace App\Http\Controllers\Api;

use App\Helpers\UploadSecurity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FeedbackStoreRequest;
use App\Http\Resources\FeedbackResource;
use App\Mail\FeedbackMail;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class FeedbackController extends Controller
{
    public function store(FeedbackStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $screenshotPath = null;

        if ($request->hasFile('screenshot')) {
            $screenshotPath = UploadSecurity::storeValidatedFile(
                $request->file('screenshot'),
                'public',
                'feedback-screenshots',
                'screenshot',
                ['image/jpeg', 'image/png', 'image/webp']
            );
        }

        $feedback = Feedback::create([
            'user_id' => $request->user()?->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'screenshot_path' => $screenshotPath,
            'page_url' => $validated['page_url'],
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $adminEmail = config('mail.from.address') ?: 'support@edubridge.com';
        Mail::to($adminEmail)->send(new FeedbackMail($feedback));

        return (new FeedbackResource($feedback))
            ->response()
            ->setStatusCode(201);
    }
}
