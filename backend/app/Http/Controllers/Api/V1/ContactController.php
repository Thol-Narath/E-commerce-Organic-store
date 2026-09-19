<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactAcknowledgmentMail;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/contact — store a customer contact/feedback message from
     * the public Contact page. Attaches the authenticated user (if any) so
     * store staff can follow up on the sender's account, and emails the
     * customer an acknowledgment so they know their message was received.
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

        $this->sendAcknowledgment($message);

        return $this->success(
            ['id' => $message->id],
            'Thank you! Your message has been received. We will get back to you soon.'
        );
    }

    /**
     * Send the customer a confirmation email. The message is already stored,
     * so a delivery failure is logged and never blocks the submission.
     */
    protected function sendAcknowledgment(ContactMessage $message): void
    {
        try {
            $settings = Setting::where('is_public', true)->get()->keyBy('key');

            Mail::to($message->email)->send(new ContactAcknowledgmentMail(
                $message,
                $settings->get('store.name')->value ?? config('app.name', 'Organic Store'),
                $settings->get('store.contact_email')->value ?? '',
                $settings->get('store.contact_phone')->value ?? '',
            ));

            $message->update(['acknowledged_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('Contact acknowledgment email failed to send', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}