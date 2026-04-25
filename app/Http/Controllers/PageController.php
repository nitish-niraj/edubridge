<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactSubmissionRequest;
use App\Mail\ContactSubmissionMail;
use App\Models\ContactSubmission;
use App\Services\SeoService;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class PageController extends Controller
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function about(): View
    {
        return $this->renderPage(
            'pages.about-us',
            'About EduBridge | Our mission and values',
            'Learn about EduBridge, our mission to improve online education, and how we support teachers and students.'
        );
    }

    public function privacy(): View
    {
        return $this->renderPage(
            'pages.privacy-policy',
            'Privacy Policy | EduBridge',
            'Read how EduBridge collects, uses, and protects personal data for all users of the platform.'
        );
    }

    public function terms(): View
    {
        return $this->renderPage(
            'pages.terms-and-conditions',
            'Terms and Conditions | EduBridge',
            'Review the terms and conditions for using EduBridge services, bookings, payments, and platform access.'
        );
    }

    public function contact(): View
    {
        return $this->renderPage(
            'pages.contact',
            'Contact EduBridge Support',
            'Reach out to the EduBridge team for support, partnerships, or product questions.'
        );
    }

    public function submitContact(ContactSubmissionRequest $request): RedirectResponse
    {
        $payload = [
            ...$request->validated(),
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ];

        try {
            $submission = ContactSubmission::query()->create($payload);
        } catch (QueryException) {
            // Allow email delivery even if the local DB is temporarily unavailable.
            $submission = new ContactSubmission($payload);
        }

        $recipient = (string) config('services.contact.to', config('mail.from.address'));

        Mail::to($recipient)->send(new ContactSubmissionMail($submission));

        return back()->with('status', 'Thanks for contacting EduBridge. Our team will respond soon.');
    }

    private function renderPage(string $view, string $title, string $description): View
    {
        $seo = $this->seoService->page([
            'title' => $title,
            'description' => $description,
            'url' => request()->url(),
        ]);

        return view($view, [
            'seoTags' => $this->seoService->render($seo),
        ]);
    }
}
