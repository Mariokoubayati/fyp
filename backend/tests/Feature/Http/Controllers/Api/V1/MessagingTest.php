<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['name' => 'Alice']);
        $this->bob = User::factory()->create(['name' => 'Bob']);
    }

    private function as(User $user): static
    {
        $this->forgetAuthenticatedRequest();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    private function start(User $from, User $to): TestResponse
    {
        return $this->as($from)->postJson(route('api.v1.conversations.store'), ['user_id' => $to->id]);
    }

    private function send(User $from, int $conversationId, string $body = 'Hi!'): TestResponse
    {
        return $this->as($from)->postJson(route('api.v1.conversations.messages.store', $conversationId), ['body' => $body]);
    }

    private function conversationId(): int
    {
        return $this->start($this->alice, $this->bob)->json('data.id');
    }

    // --- starting a conversation ---

    public function test_starting_a_conversation_returns_the_same_one_for_the_same_pair(): void
    {
        $id = $this->start($this->alice, $this->bob)->assertCreated()->assertJsonPath('data.other.name', 'Bob')->json('data.id');

        $this->start($this->alice, $this->bob)->assertOk()->assertJsonPath('data.id', $id);
        $this->start($this->bob, $this->alice)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.other.name', 'Alice');
        $this->assertSame(1, Conversation::count());
    }

    public function test_cannot_message_yourself_or_a_missing_student(): void
    {
        $this->start($this->alice, $this->alice)->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->as($this->alice)->postJson(route('api.v1.conversations.store'), ['user_id' => 999])->assertUnprocessable();
    }

    public function test_unverified_students_cannot_start_conversations(): void
    {
        $this->start(User::factory()->unverified()->create(), $this->bob)->assertForbidden();
    }

    // --- sending and reading ---

    public function test_messages_are_stored_encrypted(): void
    {
        $id = $this->conversationId();

        $this->send($this->alice, $id, 'Is the Databases book still available?')
            ->assertCreated()
            ->assertJsonPath('data.body', 'Is the Databases book still available?')
            ->assertJsonPath('data.is_mine', true);

        $raw = DB::table('messages')->value('body');
        $this->assertStringNotContainsString('Databases', $raw);
        $this->assertSame('Is the Databases book still available?', Message::first()->body);
    }

    public function test_the_recipient_gets_one_notification_per_conversation_until_they_read_it(): void
    {
        $id = $this->conversationId();

        $this->send($this->alice, $id, 'First');
        $this->send($this->alice, $id, 'Second');

        $this->assertSame(1, $this->bob->unreadNotifications()->count());
        $this->assertSame('message', $this->bob->notifications()->first()->type);
        $this->assertSame('First', $this->bob->notifications()->first()->data['preview']);
        $this->assertSame(0, $this->alice->notifications()->count());

        $this->as($this->bob)->getJson(route('api.v1.conversations.messages.index', $id))->assertOk();
        $this->assertSame(0, $this->bob->unreadNotifications()->count());

        $this->send($this->alice, $id, 'Third');
        $this->assertSame(1, $this->bob->unreadNotifications()->count());
    }

    public function test_reading_returns_messages_oldest_first_and_marks_them_read(): void
    {
        $id = $this->conversationId();
        $this->send($this->alice, $id, 'One');
        $this->send($this->bob, $id, 'Two');
        $this->send($this->alice, $id, 'Three');

        $this->as($this->bob)->getJson(route('api.v1.conversations.messages.index', $id))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.body', 'One')
            ->assertJsonPath('data.1.is_mine', true)
            ->assertJsonPath('data.2.body', 'Three');

        $this->assertSame(0, Message::where('sender_id', $this->alice->id)->whereNull('read_at')->count());
        $this->assertNull(Message::where('sender_id', $this->bob->id)->value('read_at'));
    }

    public function test_polling_with_after_returns_only_newer_messages(): void
    {
        $id = $this->conversationId();
        $first = $this->send($this->alice, $id, 'One')->json('data.id');
        $this->send($this->alice, $id, 'Two');

        $this->as($this->bob)->getJson(route('api.v1.conversations.messages.index', [$id, 'after' => $first]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Two');
    }

    public function test_outsiders_cannot_read_or_write(): void
    {
        $id = $this->conversationId();
        $carol = User::factory()->create();

        $this->as($carol)->getJson(route('api.v1.conversations.messages.index', $id))->assertForbidden();
        $this->send($carol, $id)->assertForbidden();
    }

    public function test_empty_or_too_long_messages_are_rejected(): void
    {
        $id = $this->conversationId();

        $this->send($this->alice, $id, '   ')->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->send($this->alice, $id, str_repeat('a', 2001))->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_sending_is_rate_limited(): void
    {
        $id = $this->conversationId();

        for ($i = 0; $i < 30; $i++) {
            $this->send($this->alice, $id, "Message {$i}")->assertCreated();
        }

        $this->send($this->alice, $id)->assertTooManyRequests();
    }

    // --- inbox and badges ---

    public function test_the_inbox_lists_conversations_with_previews_and_unread_counts(): void
    {
        $carol = User::factory()->create(['name' => 'Carol']);
        $withBob = $this->conversationId();
        $withCarol = $this->start($this->alice, $carol)->json('data.id');
        $this->start($this->alice, User::factory()->create());

        $this->send($this->bob, $withBob, 'Hey Alice');
        $this->send($this->bob, $withBob, 'Are you there?');
        $this->travel(1)->minutes();
        $this->send($this->alice, $withCarol, 'Hi Carol');

        $this->as($this->alice)->getJson(route('api.v1.conversations.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.other.name', 'Carol')
            ->assertJsonPath('data.0.last_message.is_mine', true)
            ->assertJsonPath('data.0.unread_count', 0)
            ->assertJsonPath('data.1.other.name', 'Bob')
            ->assertJsonPath('data.1.last_message.preview', 'Are you there?')
            ->assertJsonPath('data.1.unread_count', 2);
    }

    public function test_badges_count_unread_messages_and_other_notifications(): void
    {
        $id = $this->conversationId();
        $this->send($this->bob, $id, 'One');
        $this->send($this->bob, $id, 'Two');

        $this->as($this->alice)->getJson(route('api.v1.me.badges'))
            ->assertOk()
            ->assertExactJson(['data' => ['messages' => 2, 'notifications' => 0]]);
    }

    // --- blocking ---

    public function test_a_block_stops_messages_both_ways_until_lifted(): void
    {
        $id = $this->conversationId();
        $this->send($this->bob, $id, 'Hello');

        $this->as($this->alice)->putJson(route('api.v1.users.block.store', $this->bob))->assertNoContent();

        $this->send($this->bob, $id)->assertUnprocessable();
        $this->send($this->alice, $id)->assertUnprocessable();
        $this->start($this->bob, $this->alice)->assertUnprocessable();
        $this->as($this->alice)->getJson(route('api.v1.conversations.index'))->assertJsonPath('data.0.blocked', true);

        $this->as($this->alice)->deleteJson(route('api.v1.users.block.destroy', $this->bob))->assertNoContent();
        $this->send($this->bob, $id)->assertCreated();
    }

    public function test_you_cannot_block_yourself(): void
    {
        $this->as($this->alice)->putJson(route('api.v1.users.block.store', $this->alice))->assertUnprocessable();
    }

    // --- reports ---

    public function test_a_report_is_stored_once_per_pair(): void
    {
        $id = $this->conversationId();

        $this->as($this->bob)->postJson(route('api.v1.users.report.store', $this->alice), ['reason' => 'spam', 'conversation_id' => $id])->assertNoContent();
        $this->as($this->bob)->postJson(route('api.v1.users.report.store', $this->alice), ['reason' => 'harassment', 'details' => 'Keeps insulting me'])->assertNoContent();

        $this->assertSame(1, UserReport::count());
        $this->assertSame('harassment', UserReport::first()->reason->value);
    }

    public function test_invalid_reports_are_rejected(): void
    {
        $this->as($this->bob)->postJson(route('api.v1.users.report.store', $this->alice), ['reason' => 'boring'])->assertUnprocessable();
        $this->as($this->bob)->postJson(route('api.v1.users.report.store', $this->bob), ['reason' => 'spam'])->assertForbidden();
    }

    public function test_three_reports_stop_new_conversations_but_not_replies_until_reviewed(): void
    {
        $existing = $this->conversationId();
        foreach (User::factory()->count(3)->create() as $reporter) {
            UserReport::factory()->create(['reporter_id' => $reporter->id, 'reported_id' => $this->alice->id]);
        }

        $this->start($this->alice, User::factory()->create())->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->send($this->alice, $existing)->assertCreated();

        UserReport::query()->update(['reviewed_at' => now()]);
        $this->start($this->alice, User::factory()->create())->assertCreated();
    }

    public function test_two_reports_are_not_enough_to_restrict(): void
    {
        UserReport::factory()->count(2)->create(['reported_id' => $this->alice->id]);

        $this->start($this->alice, $this->bob)->assertCreated();
    }

    // --- notifications list ---

    public function test_notifications_can_be_listed_and_marked_read(): void
    {
        $id = $this->conversationId();
        $this->send($this->alice, $id, 'Hello Bob');

        $response = $this->as($this->bob)->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.type', 'message')
            ->assertJsonPath('data.0.data.sender.name', 'Alice')
            ->assertJsonPath('data.0.data.conversation_id', $id);

        $this->as($this->bob)->postJson(route('api.v1.notifications.read'), ['ids' => [$response->json('data.0.id')]])->assertNoContent();
        $this->assertSame(0, $this->bob->unreadNotifications()->count());
    }

    public function test_blocked_relationship_is_not_leaked_by_starting_twice(): void
    {
        Block::create(['blocker_id' => $this->bob->id, 'blocked_id' => $this->alice->id]);

        $this->start($this->alice, $this->bob)->assertUnprocessable();
        $this->assertSame(0, Conversation::count());
    }
}
