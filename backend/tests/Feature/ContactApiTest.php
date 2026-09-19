<?php

namespace Tests\Feature;

use App\Mail\ContactAcknowledgmentMail;
use App\Mail\ContactReplyMail;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_customer_can_submit_contact_message_anonymously(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/contact', [
            'name' => 'Visitor One',
            'email' => 'visitor@example.com',
            'subject' => 'Question about shipping',
            'message' => 'How long does delivery take to Phnom Penh?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Visitor One',
            'email' => 'visitor@example.com',
            'subject' => 'Question about shipping',
            'user_id' => null,
        ]);

        Mail::assertSent(ContactAcknowledgmentMail::class, function (ContactAcknowledgmentMail $mail) {
            return $mail->hasTo('visitor@example.com')
                && $mail->contactMessage->subject === 'Question about shipping';
        });
    }

    public function test_authenticated_customer_message_is_attached_to_account(): void
    {
        Mail::fake();

        $customer = User::where('role', 'customer')->first();

        $response = $this->actingAs($customer)->postJson('/api/v1/contact', [
            'name' => $customer->name,
            'email' => 'maria@example.com',
            'subject' => 'Feedback',
            'message' => 'Great store!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('contact_messages', [
            'user_id' => $customer->id,
            'email' => 'maria@example.com',
        ]);

        Mail::assertSent(ContactAcknowledgmentMail::class, function (ContactAcknowledgmentMail $mail) {
            return $mail->hasTo('maria@example.com');
        });
    }

    public function test_contact_submission_validates_required_fields(): void
    {
        $this->postJson('/api/v1/contact', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.name.0', 'The name field is required.')
            ->assertJsonPath('data.email.0', 'The email field is required.')
            ->assertJsonPath('data.subject.0', 'The subject field is required.')
            ->assertJsonPath('data.message.0', 'The message field is required.');

        $this->postJson('/api/v1/contact', [
            'name' => 'Bad Email',
            'email' => 'not-an-email',
            'subject' => 'X',
            'message' => 'Too short anyway',
        ])->assertStatus(422)
            ->assertJsonPath('data.email.0', 'The email field must be a valid email address.');
    }

    public function test_admin_can_list_contact_messages(): void
    {
        $message = ContactMessage::create([
            'user_id' => null,
            'name' => 'Visitor One',
            'email' => 'visitor@example.com',
            'subject' => 'Shipping question',
            'message' => 'Hello from the form',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->getJson('/api/v1/admin/contact-messages')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.messages.0.id', $message->id)
            ->assertJsonPath('data.messages.0.email', 'visitor@example.com')
            ->assertJsonPath('data.messages.0.is_read', false)
            ->assertJsonPath('data.messages.0.is_replied', false)
            ->assertJsonStructure(['data' => ['pagination', 'counts']]);
    }

    public function test_admin_can_filter_and_search_contact_messages(): void
    {
        ContactMessage::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'subject' => 'Order help',
            'message' => 'Where is my order?',
            'read_at' => now(),
        ]);
        ContactMessage::create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'subject' => 'Pricing',
            'message' => 'Any discounts?',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->getJson('/api/v1/admin/contact-messages?status=unreplied')
            ->assertStatus(200)
            ->assertJsonPath('data.counts.unreplied', 2);

        $this->withToken($token)->getJson('/api/v1/admin/contact-messages?q=alice')
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.email', 'alice@example.com');
    }

    public function test_admin_can_view_single_contact_message(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'visitor@example.com',
            'subject' => 'Shipping question',
            'message' => 'Hello from the form',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->getJson("/api/v1/admin/contact-messages/{$message->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Visitor One')
            ->assertJsonPath('data.email', 'visitor@example.com');
    }

    public function test_admin_can_mark_message_read_and_unread(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'visitor@example.com',
            'subject' => 'Shipping question',
            'message' => 'Hello',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->patchJson("/api/v1/admin/contact-messages/{$message->id}/read", ['read' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull(ContactMessage::find($message->id)->read_at);

        $this->withToken($token)->patchJson("/api/v1/admin/contact-messages/{$message->id}/read", ['read' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.is_read', false);

        $this->assertNull(ContactMessage::find($message->id)->refresh()->read_at);
    }

    public function test_admin_reply_saves_reply_and_emails_sender(): void
    {
        Mail::fake();

        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'real.customer@example.com',
            'subject' => 'Shipping question',
            'message' => 'How long does delivery take?',
        ]);

        $admin = User::where('role', 'admin')->first();
        $token = $this->loginAs($admin->email);

        $this->withToken($token)->postJson("/api/v1/admin/contact-messages/{$message->id}/reply", [
            'reply' => 'Delivery takes 2–3 business days. Thanks for asking!',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_replied', true)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.reply', 'Delivery takes 2–3 business days. Thanks for asking!')
            ->assertJsonPath('data.replied_by.id', $admin->id);

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'replied_by' => $admin->id,
        ]);

        Mail::assertSent(ContactReplyMail::class, function (ContactReplyMail $mail) use ($message) {
            return $mail->hasTo('real.customer@example.com')
                && $mail->reply === 'Delivery takes 2–3 business days. Thanks for asking!';
        });
    }

    public function test_admin_reply_requires_reply_text(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'real.customer@example.com',
            'subject' => 'Shipping question',
            'message' => 'How long does delivery take?',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->postJson("/api/v1/admin/contact-messages/{$message->id}/reply", [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reply.0', 'The reply field is required.');
    }

    public function test_admin_contact_endpoints_reject_customers_and_staff(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'real.customer@example.com',
            'subject' => 'Shipping question',
            'message' => 'How long does delivery take?',
        ]);

        $customerToken = $this->loginAs('maria@example.com');
        $staffToken = $this->loginAs('staff@organicstore.test');

        $this->withToken($customerToken)->getJson('/api/v1/admin/contact-messages')->assertStatus(403);
        $this->withToken($customerToken)->getJson("/api/v1/admin/contact-messages/{$message->id}")->assertStatus(403);
        $this->withToken($customerToken)->postJson("/api/v1/admin/contact-messages/{$message->id}/reply", ['reply' => 'hi'])
            ->assertStatus(403);
        $this->withToken($customerToken)->deleteJson("/api/v1/admin/contact-messages/{$message->id}")->assertStatus(403);

        $this->withToken($staffToken)->getJson('/api/v1/admin/contact-messages')->assertStatus(403);
    }

    public function test_contact_admin_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/contact-messages')->assertStatus(401);
    }

    public function test_admin_can_delete_contact_message(): void
    {
        $message = ContactMessage::create([
            'name' => 'Visitor One',
            'email' => 'real.customer@example.com',
            'subject' => 'Shipping question',
            'message' => 'Delete me',
        ]);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->deleteJson("/api/v1/admin/contact-messages/{$message->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    private function loginAs(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }
}