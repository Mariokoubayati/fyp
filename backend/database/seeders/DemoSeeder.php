<?php

namespace Database\Seeders;

use App\Enums\ListingCategory;
use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Enums\LoanStatus;
use App\Enums\TrustEventType;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Loan;
use App\Models\StudyGroup;
use App\Models\User;
use App\Models\Vouch;
use App\Services\Trust\TrustService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * A realistic campus for demos, device testing and the usability study.
 *
 *     php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 *
 * Every student's password is "password". The demo login is 202300000@ua.edu.lb.
 */
class DemoSeeder extends Seeder
{
    private const STUDENTS = 40;

    /**
     * @var array<string, list<string>>
     */
    private const LISTING_TITLES = [
        'books' => ['%s textbook', 'Used %s book, good condition', '%s reference book'],
        'notes' => ['%s full semester notes', '%s summary for the final', '%s past exams with solutions'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const GENERAL_ITEMS = [
        'electronics' => ['Scientific calculator', 'USB drive 64GB', 'Wireless mouse', 'Laptop stand'],
        'supplies' => ['Lab coat', 'Notebook bundle', 'Engineering ruler set', 'Drawing kit'],
        'other' => ['Desk lamp', 'Backpack', 'Bike lock', 'Mini fridge'],
    ];

    private const LOCATIONS = ['Library, 2nd floor', 'Cafeteria', 'Room B204', 'Study hall', 'Online (Teams)'];

    public function run(TrustService $trust): void
    {
        $this->call(CourseSeeder::class);
        $courses = Course::all();

        $demo = User::firstWhere('email', '202300000@ua.edu.lb')
            ?? User::factory()->create(['name' => 'Test User', 'email' => '202300000@ua.edu.lb']);
        $demo->forceFill(['major' => 'Computer Science', 'study_year' => 3, 'created_at' => now()->subMonths(3)])->save();
        $demo->courses()->syncWithoutDetaching($courses->whereIn('code', ['CSC-301', 'CSC-302', 'CSC-303', 'STA-201'])->modelKeys());

        $students = $this->students($courses)->push($demo);

        $this->pastGroups($students, $courses);
        $this->upcomingGroups($students, $courses);
        $this->listings($students, $trust);
        $this->loans($students, $trust);
        $this->vouches($students, $trust);
        $this->conversations($demo, $students->reject(fn (User $s): bool => $s->is($demo)));

        $students->each(fn (User $student) => $trust->refresh($student));
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, User>
     */
    private function students(Collection $courses): Collection
    {
        $majors = [
            'Computer Science' => ['CSC', 'MTH'],
            'Business' => ['BUS', 'STA'],
        ];

        return User::factory()->count(self::STUDENTS)->create()->each(function (User $student) use ($courses, $majors): void {
            $major = fake()->randomElement(array_keys($majors));
            $student->forceFill([
                'major' => $major,
                'study_year' => fake()->numberBetween(1, 4),
                'created_at' => now()->subDays(fake()->numberBetween(20, 200)),
            ])->save();

            $own = $courses->filter(fn (Course $course): bool => in_array(substr($course->code, 0, 3), $majors[$major], true));
            $picked = $own->random(min(4, $own->count()))->push($courses->firstWhere('code', 'ENG-101'));
            $student->courses()->syncWithoutDetaching($picked->modelKeys());
        });
    }

    /**
     * Finished study sessions, so students have partners they studied with before.
     *
     * @param  Collection<int, User>  $students
     * @param  Collection<int, Course>  $courses
     */
    private function pastGroups(Collection $students, Collection $courses): void
    {
        foreach (range(1, 15) as $i) {
            $course = $courses->random();
            $enrolled = $students->filter(fn (User $s): bool => $s->courses->contains($course));
            if ($enrolled->count() < 2) {
                continue;
            }

            $group = StudyGroup::factory()->past()->create([
                'course_id' => $course->id,
                'creator_id' => $enrolled->random()->id,
                'title' => "{$course->name} review session",
                'location' => fake()->randomElement(self::LOCATIONS),
                'capacity' => 6,
            ]);
            $group->members()->syncWithoutDetaching($enrolled->random(min(4, $enrolled->count()))->modelKeys());
        }
    }

    /**
     * @param  Collection<int, User>  $students
     * @param  Collection<int, Course>  $courses
     */
    private function upcomingGroups(Collection $students, Collection $courses): void
    {
        $topics = ['Midterm prep', 'Problem set', 'Final exam review', 'Lab help', 'Weekly study session'];

        foreach (range(1, 25) as $i) {
            $course = $courses->random();
            $enrolled = $students->filter(fn (User $s): bool => $s->courses->contains($course));
            if ($enrolled->isEmpty()) {
                continue;
            }

            $group = StudyGroup::factory()->create([
                'course_id' => $course->id,
                'creator_id' => $enrolled->random()->id,
                'title' => fake()->randomElement($topics).": {$course->name}",
                'location' => fake()->randomElement(self::LOCATIONS),
                'starts_at' => now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(9, 19), 0),
                'capacity' => fake()->numberBetween(4, 8),
            ]);
            $group->members()->syncWithoutDetaching($enrolled->random(min(fake()->numberBetween(0, 3), $enrolled->count()))->modelKeys());
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function listings(Collection $students, TrustService $trust): void
    {
        foreach ($students as $seller) {
            for ($i = fake()->numberBetween(0, 2); $i > 0; $i--) {
                $listing = $this->listingFor($seller);

                if (fake()->boolean(30)) {
                    $buyer = $students->reject(fn (User $s): bool => $s->is($seller))->random();
                    $listing->forceFill(['status' => ListingStatus::Sold, 'buyer_id' => $buyer->id, 'sold_at' => now()->subDays(fake()->numberBetween(1, 30))])->save();
                    $trust->record($seller, TrustEventType::SaleCompleted, $listing, counterparty: $buyer);
                    $trust->record($buyer, TrustEventType::PurchaseCompleted, $listing, counterparty: $seller);

                    $stars = fake()->numberBetween(3, 5);
                    ListingRating::forceCreate(['listing_id' => $listing->id, 'rater_id' => $buyer->id, 'ratee_id' => $seller->id, 'stars' => $stars]);
                    $trust->record($seller, TrustEventType::RatingReceived, $listing, $stars, counterparty: $buyer);
                }
            }
        }
    }

    private function listingFor(User $seller): Listing
    {
        $category = fake()->randomElement(['books', 'books', 'notes', 'notes', 'electronics', 'supplies', 'other']);
        $course = in_array($category, ['books', 'notes'], true) ? $seller->courses->random() : null;
        $title = $course
            ? sprintf(fake()->randomElement(self::LISTING_TITLES[$category]), $course->name)
            : fake()->randomElement(self::GENERAL_ITEMS[$category]);

        return Listing::factory()->create([
            'seller_id' => $seller->id,
            'course_id' => $course?->id,
            'title' => $title,
            'category' => ListingCategory::from($category),
            'condition' => fake()->randomElement(ListingCondition::cases()),
            'price' => fake()->numberBetween($category === 'notes' ? 3 : 8, $category === 'electronics' ? 60 : 35),
            'created_at' => now()->subDays(fake()->numberBetween(0, 20)),
        ]);
    }

    /**
     * Repaid loans between different students, a few late ones and open requests.
     *
     * @param  Collection<int, User>  $students
     */
    private function loans(Collection $students, TrustService $trust): void
    {
        foreach (range(1, 30) as $i) {
            [$borrower, $lender] = $students->random(2)->all();
            $start = now()->subDays(fake()->numberBetween(20, 80));
            $late = fake()->boolean(15);

            $loan = Loan::factory()->create([
                'borrower_id' => $borrower->id,
                'lender_id' => $lender->id,
                'amount' => fake()->randomElement([5, 10, 15, 20]),
                'purpose' => fake()->randomElement(['Lunch', 'Printing', 'Transport', 'Books']),
                'status' => LoanStatus::Repaid,
                'activated_at' => $start,
                'due_date' => $start->copy()->addDays(10)->toDateString(),
                'repaid_at' => $late ? $start->copy()->addDays(14) : $start->copy()->addDays(fake()->numberBetween(3, 9)),
            ]);

            if ($late) {
                $trust->record($borrower, TrustEventType::LoanOverdue, $loan, counterparty: $lender);
            }
            $trust->record($borrower, $late ? TrustEventType::LoanRepaidLate : TrustEventType::LoanRepaidOnTime, $loan, counterparty: $lender);
        }

        foreach (range(1, 6) as $i) {
            Loan::factory()->create([
                'borrower_id' => $students->random()->id,
                'amount' => fake()->randomElement([5, 10, 15]),
                'purpose' => fake()->randomElement(['Lunch', 'Printing', 'Transport']),
                'due_date' => now()->addDays(fake()->numberBetween(7, 20))->toDateString(),
            ]);
        }
    }

    /**
     * A few chats for the demo login, one of them with an unread reply.
     *
     * @param  Collection<int, User>  $others
     */
    private function conversations(User $demo, Collection $others): void
    {
        $scripts = [
            [['them', 'Hi! Is your Data Structures summary still available?'], ['me', 'Yes it is, $5. I can bring it tomorrow.'], ['them', 'Perfect, see you at the library at 11.']],
            [['me', 'Are you joining the Database Systems review on Thursday?'], ['them', 'Yes! Can you share the practice questions before?']],
            [['them', 'Thanks for the loan, I will pay you back on Friday.']],
        ];

        foreach ($others->random(count($scripts))->values() as $i => $other) {
            $conversation = Conversation::between($demo, $other);
            $sentAt = now()->subHours(count($scripts[$i]) * 3 + $i * 24);

            foreach ($scripts[$i] as [$who, $body]) {
                $sentAt = $sentAt->copy()->addHours(2);
                $message = $conversation->messages()->create(['sender_id' => ($who === 'me' ? $demo : $other)->id, 'body' => $body]);
                $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt, 'read_at' => $i === 0 && $who === 'them' ? null : $sentAt])->save();
            }

            $conversation->forceFill(['last_message_at' => $sentAt])->save();
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function vouches(Collection $students, TrustService $trust): void
    {
        foreach (range(1, 25) as $i) {
            [$voucher, $vouchee] = $students->random(2)->all();
            if (Vouch::where(['voucher_id' => $voucher->id, 'vouchee_id' => $vouchee->id])->exists()) {
                continue;
            }
            Vouch::forceCreate(['voucher_id' => $voucher->id, 'vouchee_id' => $vouchee->id]);
            $trust->record($vouchee, TrustEventType::VouchReceived, $voucher, counterparty: $voucher);
        }
    }
}
