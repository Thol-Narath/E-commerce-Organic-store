<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin contact inbox (admin only).
 *
 * Customers submit messages through the public /contact endpoint; staff
 * manage the inbox here: read, reply and delete messages. Replies are stored
 * on the message (thread-style) so the conversation history survives even
 * after the customer's message is marked as handled.
 */
class AdminContactController extends Controller
{
    use ApiResponse, Paginates;

    /**
     * GET /api/v1/admin/contact-messages — paginated, filterable inbox.
     *
     * Query params:
     *   q       — search by name, email or subject
     *   status  — all | unread | read | replied | unreplied
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactMessage::query()
            ->with(['user', 'repliedBy'])
            ->latest();

        if ($term = trim((string) $request->query('q'))) {
            $query->search($term);
        }

        match ($request->query('status', 'all')) {
            'unread' => $query->unread(),
            'read' => $query->read(),
            'replied' => $query->replied(),
            'unreplied' => $query->unreplied(),
            default => null,
        };

        $paginator = $query->paginate($this->perPage($request, 15));

        return $this->success([
            'messages' => ContactMessageResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
            'counts' => [
                'total' => ContactMessage::count(),
                'unread' => ContactMessage::unread()->count(),
                'unreplied' => ContactMessage::unreplied()->count(),
            ],
        ], 'Contact messages retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/contact-messages/{contactMessage} — full message detail
     * including the sender's reply thread.
     */
    public function show(ContactMessage $contactMessage): JsonResponse
    {
        return $this->success(
            new ContactMessageResource($contactMessage->load(['user', 'repliedBy'])),
            'Contact message retrieved successfully.'
        );
    }

    /**
     * PATCH /api/v1/admin/contact-messages/{contactMessage}/read — mark as read.
     * Passing read=false marks the message as unread again.
     */
    public function markRead(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $request->validate([
            'read' => ['sometimes', 'boolean'],
        ]);

        $read = $request->boolean('read', true);
        $contactMessage->update(['read_at' => $read ? now() : null]);

        return $this->success(
            new ContactMessageResource($contactMessage->load(['user', 'repliedBy'])),
            $read ? 'Message marked as read.' : 'Message marked as unread.'
        );
    }

    /**
     * POST /api/v1/admin/contact-messages/{contactMessage}/reply — store the
     * staff reply, mark the message as read, and email the reply to the
     * customer. The reply is always saved even if the email fails (the failure
     * is logged and surfaced to the admin in the response).
     */
    public function reply(Request $request, ContactMessage $contactMessage): JsonResponse
    {
        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:5000'],
        ]);

        $reply = trim($validated['reply']);

        DB::transaction(function () use ($contactMessage, $request, $reply) {
            $contactMessage->update([
                'reply' => $reply,
                'replied_by' => $request->user()->id,
                'replied_at' => now(),
                'read_at' => now(),
            ]);
        });

        $emailSent = $this->sendReplyEmail($contactMessage, $reply);

        $message = $emailSent
            ? 'Reply sent to the customer via email.'
            : 'Reply saved, but the email could not be delivered. Check your mail settings and send again.';

        return $this->success(
            new ContactMessageResource($contactMessage->load(['user', 'repliedBy'])),
            $message
        );
    }

    /**
     * Deliver the reply email to the customer. Returns true on success; on
     * failure the exception is logged so it can be fixed without losing the
     * stored reply.
     */
    protected function sendReplyEmail(ContactMessage $contactMessage, string $reply): bool
    {
        try {
            $settings = Setting::where('is_public', true)->get()->keyBy('key');

            Mail::to($contactMessage->email)->send(new ContactReplyMail(
                $contactMessage,
                $reply,
                $settings->get('store.name')->value ?? config('app.name', 'Organic Store'),
                $settings->get('store.contact_email')->value ?? '',
                $settings->get('store.contact_phone')->value ?? '',
            ));

            return true;
        } catch (\Throwable $e) {
            Log::error('Contact reply email failed to send', [
                'message_id' => $contactMessage->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * DELETE /api/v1/admin/contact-messages/{contactMessage} — remove a message
     * from the inbox.
     */
    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return $this->success(null, 'Contact message deleted successfully.');
    }
}