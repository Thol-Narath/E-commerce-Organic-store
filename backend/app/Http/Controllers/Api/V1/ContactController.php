<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/contact — store a customer contact/feedback message from
     * the public Contact page. Attaches the authenticated user (if any) so
     * store staff can follow up on the sender's account.
     *
     * Returns the stored message id so the customer UI can confirm delivery.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'subject' => trim($validated['subject']),
            'message' => trim($validated['message']),
        ]);

        return $this->success(
            ['id' => $message->id],
            'Thank you! Your message has been received. We will get back to you soon.'
        );
    }
}