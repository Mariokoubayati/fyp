<?php

namespace Tests\Feature\Services\Badges;

use App\Enums\Badge;
use App\Enums\TrustEventType;
use App\Models\Event;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Loan;
use App\Models\StudyGroup;
use App\Models\TrustEvent;
use App\Models\User;
use App\Notifications\BadgeEarnedNotification;
use App\Services\Badges\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function badgesOf(User $user): array
    {
        return $user->badges()->pluck('badge')->map(fn (Badge $badge): string => $badge->value)->sort()->values()->all();
    }

    public function test_a_new_trust_event_awards_the_badge_once_with_one_notification(): void
    {
        Notification::fake();
        $seller = User::factory()->create();

        TrustEvent::factory()->for($seller)->ofType(TrustEventType::SaleCompleted)->create();
        TrustEvent::factory()->for($seller)->ofType(TrustEventType::SaleCompleted)->create();

        $this->assertSame(['first_sale'], $this->badgesOf($seller));
        Notification::assertSentToTimes($seller, BadgeEarnedNotification::class, 1);
        Notification::assertSentTo($seller, BadgeEarnedNotification::class, fn ($n) => $n->toArray($seller)['title'] === 'First sale');
    }

    public function test_loans_the_anti_collusion_rules_discounted_do_not_count(): void
    {
        $borrower = User::factory()->create();
        $lenders = User::factory()->count(3)->create();

        foreach ($lenders as $i => $lender) {
            TrustEvent::factory()->for($borrower)->ofType(TrustEventType::LoanRepaidOnTime)->create([
                'subject_type' => (new Loan)->getMorphClass(),
                'subject_id' => Loan::factory()->create(['borrower_id' => $borrower->id])->id,
                'counterparty_id' => $lender->id,
                'discount_reason' => $i === 0 ? 'loan_too_short' : null,
            ]);
        }

        $this->assertNotContains('reliable_borrower', $this->badgesOf($borrower));
        $this->assertSame(2, app(BadgeService::class)->progress($borrower)['reliable_borrower']['value']);
    }

    public function test_the_lender_earns_helping_hand_from_three_different_borrowers_only(): void
    {
        $lender = User::factory()->create();
        $sameBorrower = User::factory()->create();

        foreach ([$sameBorrower, $sameBorrower, User::factory()->create()] as $borrower) {
            TrustEvent::factory()->for($borrower)->ofType(TrustEventType::LoanRepaidOnTime)->create([
                'subject_type' => (new Loan)->getMorphClass(),
                'subject_id' => Loan::factory()->create(['borrower_id' => $borrower->id])->id,
                'counterparty_id' => $lender->id,
            ]);
        }
        $this->assertNotContains('helping_hand', $this->badgesOf($lender));

        TrustEvent::factory()->ofType(TrustEventType::LoanRepaidLate)->create([
            'subject_type' => (new Loan)->getMorphClass(),
            'subject_id' => Loan::factory()->create()->id,
            'counterparty_id' => $lender->id,
        ]);
        $this->assertContains('helping_hand', $this->badgesOf($lender));
    }

    public function test_top_seller_needs_five_sales_and_a_good_average_rating(): void
    {
        $seller = User::factory()->create();
        $listings = Listing::factory()->count(5)->create(['seller_id' => $seller->id]);
        foreach ($listings as $i => $listing) {
            ListingRating::factory()->create(['listing_id' => $listing->id, 'ratee_id' => $seller->id, 'stars' => $i === 0 ? 3 : 4]);
        }

        foreach ($listings as $listing) {
            TrustEvent::factory()->for($seller)->ofType(TrustEventType::SaleCompleted)->create(['subject_id' => $listing->id]);
        }
        $this->assertNotContains('top_seller', $this->badgesOf($seller));

        ListingRating::query()->update(['stars' => 5]);
        app(BadgeService::class)->check($seller);
        $this->assertContains('top_seller', $this->badgesOf($seller));
    }

    public function test_joining_a_third_study_group_earns_study_buddy_right_away(): void
    {
        $student = User::factory()->create();
        StudyGroup::factory()->count(2)->create(['creator_id' => $student->id]);
        $this->assertNotContains('study_buddy', $this->badgesOf($student));

        $group = StudyGroup::factory()->create();
        $student->courses()->attach($group->course_id);
        $this->actingAs($student, 'api')->putJson(route('api.v1.study-groups.join', $group))->assertSuccessful();

        $this->assertContains('study_buddy', $this->badgesOf($student));
    }

    public function test_the_host_earns_event_host_when_the_fifth_student_says_they_are_going(): void
    {
        $event = Event::factory()->create(['capacity' => null]);
        $event->attendees()->attach(User::factory()->count(4)->create());

        $this->actingAs(User::factory()->create(), 'api')->putJson(route('api.v1.events.rsvp', $event))->assertOk();

        $this->assertContains('event_host', $this->badgesOf($event->creator));
    }

    public function test_quietly_awards_without_notifications(): void
    {
        Notification::fake();
        $seller = User::factory()->create();

        BadgeService::quietly(fn () => TrustEvent::factory()->for($seller)->ofType(TrustEventType::SaleCompleted)->create());

        $this->assertSame(['first_sale'], $this->badgesOf($seller));
        Notification::assertNothingSent();
    }
}
