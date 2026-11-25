<?php

namespace Database\Seeders;

use App\Enums\CampusTag;
use App\Enums\ListingCategory;
use App\Enums\ListingCondition;
use App\Enums\ListingStatus;
use App\Enums\LoanStatus;
use App\Enums\ProjectMemberStatus;
use App\Enums\TrustEventType;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Event;
use App\Models\Listing;
use App\Models\ListingRating;
use App\Models\Loan;
use App\Models\Project;
use App\Models\StudyGroup;
use App\Models\User;
use App\Models\Vouch;
use App\Notifications\NewMessageNotification;
use App\Services\Trust\TrustService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A realistic campus for demos, device testing and the usability study.
 *
 *     php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 *
 * Every student's password is "password". Fixed test accounts (used by the test plan and
 * `php artisan app:e2e`):
 *
 *   202300000@ua.edu.lb  Test User        verified, main tester
 *   202300001@ua.edu.lb  Test User Two    verified, same courses (the "other student")
 *   202300002@ua.edu.lb  Test User Three  verified, same courses (an outsider for permission checks)
 *   202300009@ua.edu.lb  Unverified User  email not verified
 *
 * It also adds upcoming events with people going, projects looking for members, and
 * interests and skills on most profiles (fixed ones for the three test accounts).
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

    /**
     * Realistic student prices in dollars, by category.
     *
     * @var array<string, array{int, int}>
     */
    private const PRICE_RANGES = [
        'books' => [8, 35],
        'notes' => [3, 10],
        'electronics' => [10, 60],
        'supplies' => [3, 20],
        'other' => [5, 40],
    ];

    private const LOCATIONS = ['Library, 2nd floor', 'Cafeteria', 'Room B204', 'Study hall', 'Online (Teams)'];

    /**
     * Upcoming events by topic: [title, location, hour].
     *
     * @var array<string, list<array{string, string, int}>>
     */
    private const EVENTS = [
        'tech' => [['Intro to Git and GitHub workshop', 'Computer lab 3', 14], ['Mobile app hackathon', 'Main auditorium', 10], ['AI study circle', 'Room B204', 16]],
        'career' => [['CV and LinkedIn clinic', 'Career center', 12], ['Meet the alumni: software careers', 'Main auditorium', 15]],
        'sports' => [['Five-a-side football', 'Sports field', 18], ['Campus 5K fun run', 'Main gate', 8]],
        'social' => [['Board games night', 'Cafeteria', 19], ['Welcome back mixer', 'Student lounge', 18]],
        'arts' => [['Photography walk', 'Main gate', 16], ['Open mic night', 'Student lounge', 20]],
        'culture' => [['Lebanese food fair', 'Cafeteria', 13]],
        'volunteering' => [['Beach clean-up', 'Ramlet al Baida', 9], ['Blood donation drive', 'Health center', 11]],
        'academic' => [['Calculus exam review talk', 'Room A101', 15], ['Research methods seminar', 'Library, 2nd floor', 13]],
    ];

    /**
     * Projects looking for members: [title, description, skills needed, tags].
     *
     * @var list<array{string, string, list<string>, list<string>}>
     */
    private const PROJECTS = [
        ['Campus lost and found app', 'A small app where students post things they lost or found on campus.', ['React Native', 'Laravel', 'UI design'], ['tech']],
        ['Student startup pitch deck', 'Preparing a pitch for the university startup competition in December.', ['Marketing', 'Public speaking', 'Excel'], ['career']],
        ['Short film for the arts festival', 'A five minute film about student life, to show at the spring festival.', ['Video editing', 'Photography', 'Writing'], ['arts', 'culture']],
        ['Charity run organizing team', 'Organizing a 5K run to raise money for a local NGO.', ['Event planning', 'Marketing'], ['volunteering', 'sports']],
        ['Machine learning study project', 'Building a model that predicts exam stress from sleep data, for practice.', ['Python', 'Machine learning'], ['tech', 'academic']],
        ['Business club website', 'Redesigning the Business club website and adding an events page.', ['UI design', 'Laravel'], ['tech', 'career']],
        ['Podcast about campus life', 'A weekly podcast interviewing students and teachers.', ['Writing', 'Public speaking', 'Video editing'], ['social', 'culture']],
        ['Arduino weather station', 'A small weather station on the engineering building roof.', ['Arduino', 'Python'], ['tech', 'academic']],
    ];

    /**
     * Skills students list on their profile, by major.
     *
     * @var array<string, list<string>>
     */
    private const SKILLS = [
        'Computer Science' => ['Laravel', 'Python', 'React Native', 'SQL', 'Machine learning', 'UI design'],
        'Business' => ['Marketing', 'Excel', 'Public speaking', 'Finance', 'Event planning'],
    ];

    public function run(TrustService $trust): void
    {
        // Same seed, same campus: every reset gives the same students, scores and chats,
        // so demos, the usability study and the test plan always start from the same data.
        fake()->seed(2026);

        $this->call(CourseSeeder::class);
        $courses = Course::all();

        $demo = User::firstWhere('email', '202300000@ua.edu.lb')
            ?? User::factory()->create(['name' => 'Test User', 'email' => '202300000@ua.edu.lb']);
        $demo->forceFill(['major' => 'Computer Science', 'study_year' => 3, 'created_at' => now()->subMonths(3)])->save();
        $demo->courses()->syncWithoutDetaching($courses->whereIn('code', ['CSC-301', 'CSC-302', 'CSC-303', 'STA-201'])->modelKeys());

        $this->testAccount('202300001@ua.edu.lb', 'Test User Two', $demo->courses->modelKeys());
        $this->testAccount('202300002@ua.edu.lb', 'Test User Three', $demo->courses->modelKeys());
        $this->testAccount('202300009@ua.edu.lb', 'Unverified User', [], verified: false);

        $students = $this->students($courses)->push($demo);

        $this->pastGroups($students, $courses);
        $this->upcomingGroups($students, $courses);
        $this->listings($students, $trust);
        $this->loans($students, $trust);
        $this->vouches($students, $trust);
        $this->conversations($demo, $students->reject(fn (User $s): bool => $s->is($demo)));

        // Added after everything above so the earlier demo data stays exactly the same.
        $this->interestsAndSkills($students);
        $this->events($students, $courses);
        $this->projects($students);

        $students->each(fn (User $student) => $trust->refresh($student));
    }

    /**
     * A fixed account with a known email, so testers always know who to sign in as.
     *
     * @param  list<int>  $courseIds
     */
    private function testAccount(string $email, string $name, array $courseIds, bool $verified = true): User
    {
        $user = User::firstWhere('email', $email) ?? User::factory()->create(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'major' => 'Computer Science',
            'study_year' => 3,
            'created_at' => now()->subMonths(3),
            'email_verified_at' => $verified ? now()->subMonths(3) : null,
        ])->save();
        $user->courses()->syncWithoutDetaching($courseIds);

        return $user;
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
            $picked = $this->pickMany($own, min(4, $own->count()))->push($courses->firstWhere('code', 'ENG-101'));
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
            $course = $this->pick($courses);
            $enrolled = $students->filter(fn (User $s): bool => $s->courses->contains($course));
            if ($enrolled->count() < 2) {
                continue;
            }

            $group = StudyGroup::factory()->past()->create([
                'course_id' => $course->id,
                'creator_id' => $this->pick($enrolled)->id,
                'title' => "{$course->name} review session",
                'location' => fake()->randomElement(self::LOCATIONS),
                'capacity' => 6,
            ]);
            $group->members()->syncWithoutDetaching($this->pickMany($enrolled, min(4, $enrolled->count()))->modelKeys());
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
            $course = $this->pick($courses);
            $enrolled = $students->filter(fn (User $s): bool => $s->courses->contains($course));
            if ($enrolled->isEmpty()) {
                continue;
            }

            $group = StudyGroup::factory()->create([
                'course_id' => $course->id,
                'creator_id' => $this->pick($enrolled)->id,
                'title' => fake()->randomElement($topics).": {$course->name}",
                'location' => fake()->randomElement(self::LOCATIONS),
                'starts_at' => $this->campusTime(fake()->numberBetween(1, 14), fake()->numberBetween(9, 19)),
                'capacity' => fake()->numberBetween(4, 8),
            ]);
            $group->members()->syncWithoutDetaching($this->pickMany($enrolled, min(fake()->numberBetween(0, 3), $enrolled->count()))->modelKeys());
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
                    $buyer = $this->pick($students->reject(fn (User $s): bool => $s->is($seller)));
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
        $course = in_array($category, ['books', 'notes'], true) ? $this->pick($seller->courses) : null;
        $title = $course
            ? sprintf(fake()->randomElement(self::LISTING_TITLES[$category]), $course->name)
            : fake()->randomElement(self::GENERAL_ITEMS[$category]);

        return Listing::factory()->create([
            'seller_id' => $seller->id,
            'course_id' => $course?->id,
            'title' => $title,
            'category' => ListingCategory::from($category),
            'condition' => fake()->randomElement(ListingCondition::cases()),
            'price' => fake()->numberBetween(...self::PRICE_RANGES[$category]),
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
            [$borrower, $lender] = $this->pickMany($students, 2)->all();
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
                'borrower_id' => $this->pick($students)->id,
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

        foreach ($this->pickMany($others, count($scripts))->values() as $i => $other) {
            $conversation = Conversation::between($demo, $other);
            $sentAt = now()->subHours(count($scripts[$i]) * 3 + $i * 24);

            foreach ($scripts[$i] as [$who, $body]) {
                $sentAt = $sentAt->copy()->addHours(2);
                $message = $conversation->messages()->create(['sender_id' => ($who === 'me' ? $demo : $other)->id, 'body' => $body]);
                $message->forceFill(['created_at' => $sentAt, 'updated_at' => $sentAt, 'read_at' => $i === 0 && $who === 'them' ? null : $sentAt])->save();
            }

            $conversation->forceFill(['last_message_at' => $sentAt])->save();

            // The chat with unread messages also shows up under the bell.
            if ($i === 0) {
                $demo->notify(new NewMessageNotification($conversation->messages()->where('sender_id', $other->id)->latest('id')->first()));
            }
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function vouches(Collection $students, TrustService $trust): void
    {
        foreach (range(1, 25) as $i) {
            [$voucher, $vouchee] = $this->pickMany($students, 2)->all();
            if (Vouch::where(['voucher_id' => $voucher->id, 'vouchee_id' => $vouchee->id])->exists()) {
                continue;
            }
            Vouch::forceCreate(['voucher_id' => $voucher->id, 'vouchee_id' => $vouchee->id]);
            $trust->record($vouchee, TrustEventType::VouchReceived, $voucher, counterparty: $voucher);
        }
    }

    /**
     * Collection::random() uses PHP's secure randomizer, which cannot be seeded. Faker can.
     */
    /**
     * Fixed interests and skills for the test accounts; most demo students fill theirs in, some leave them empty.
     *
     * @param  Collection<int, User>  $students
     */
    private function interestsAndSkills(Collection $students): void
    {
        $fixed = [
            '202300000@ua.edu.lb' => [['tech', 'career'], ['Laravel', 'SQL']],
            '202300001@ua.edu.lb' => [['tech', 'sports'], ['Python', 'UI design']],
            '202300002@ua.edu.lb' => [['arts', 'social'], ['Photography']],
        ];

        foreach ($fixed as $email => [$interests, $skills]) {
            User::firstWhere('email', $email)?->forceFill(['interests' => $interests, 'skills' => $skills])->save();
        }

        $tags = array_map(fn (CampusTag $tag): string => $tag->value, CampusTag::cases());

        $students
            ->reject(fn (User $student): bool => array_key_exists($student->email, $fixed))
            ->each(function (User $student) use ($tags): void {
                $student->forceFill([
                    'interests' => fake()->boolean(75) ? fake()->randomElements($tags, fake()->numberBetween(1, 3)) : [],
                    'skills' => fake()->boolean(70) ? fake()->randomElements(self::SKILLS[$student->major] ?? self::SKILLS['Computer Science'], fake()->numberBetween(1, 3)) : [],
                ])->save();
            });
    }

    /**
     * Upcoming events with people going, plus a few past ones so students have a history.
     *
     * @param  Collection<int, User>  $students
     * @param  Collection<int, Course>  $courses
     */
    private function events(Collection $students, Collection $courses): void
    {
        $testers = User::query()->whereIn('email', ['202300000@ua.edu.lb', '202300001@ua.edu.lb', '202300002@ua.edu.lb'])->pluck('id')->all();
        $others = $students->reject(fn (User $student): bool => in_array($student->id, $testers, true))->values();

        foreach (self::EVENTS as $tag => $events) {
            foreach ($events as [$title, $location, $hour]) {
                $startsAt = $this->campusTime(fake()->numberBetween(2, 20), $hour);
                $event = Event::factory()->create([
                    'creator_id' => $this->pick($others)->id,
                    'course_id' => $tag === 'academic' ? $courses->firstWhere('code', fake()->randomElement(['MTH-101', 'STA-201']))?->id : null,
                    'title' => $title,
                    'description' => fake()->boolean(70) ? fake()->sentence(12) : null,
                    'location' => $location,
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addHours(fake()->numberBetween(1, 3)),
                    'capacity' => fake()->boolean(40) ? fake()->numberBetween(10, 40) : null,
                    'tags' => array_values(array_unique([$tag, ...(fake()->boolean(30) ? [fake()->randomElement(['social', 'career', 'academic'])] : [])])),
                ]);
                $interested = $others->filter(fn (User $student): bool => in_array($tag, $student->interests ?? [], true));
                $event->attendees()->syncWithoutDetaching($this->pickMany($interested, min(fake()->numberBetween(1, 6), $interested->count()))->modelKeys());
            }
        }

        foreach (['Freshers welcome party' => 'social', 'Programming contest' => 'tech', 'Charity bake sale' => 'volunteering'] as $title => $tag) {
            $event = Event::factory()->create([
                'creator_id' => $this->pick($others)->id,
                'title' => $title,
                'location' => 'Student lounge',
                'starts_at' => $this->campusTime(-fake()->numberBetween(7, 40), 18),
                'ends_at' => null,
                'tags' => [$tag],
            ]);
            $event->attendees()->syncWithoutDetaching($this->pickMany($others, 8)->modelKeys());
        }
    }

    /**
     * Projects looking for members, some with a team and pending requests already.
     *
     * @param  Collection<int, User>  $students
     */
    private function projects(Collection $students): void
    {
        $testers = User::query()->whereIn('email', ['202300000@ua.edu.lb', '202300001@ua.edu.lb', '202300002@ua.edu.lb'])->pluck('id')->all();
        $others = $students->reject(fn (User $student): bool => in_array($student->id, $testers, true))->values();

        foreach (self::PROJECTS as $i => [$title, $description, $skills, $tags]) {
            $owner = $this->pick($others);
            $project = Project::factory()->create([
                'owner_id' => $owner->id,
                'title' => $title,
                'description' => $description,
                'skills_needed' => $skills,
                'tags' => $tags,
                'team_size' => fake()->numberBetween(3, 6),
                'created_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);
            $candidates = $others->reject(fn (User $student): bool => $student->is($owner));
            $team = $this->pickMany($candidates, fake()->numberBetween(0, 1));
            $project->people()->syncWithoutDetaching($team->mapWithKeys(fn (User $member): array => [$member->id => ['status' => ProjectMemberStatus::Accepted->value]])->all());
            if ($i % 3 === 0) {
                $waiting = $this->pick($candidates->reject(fn (User $student): bool => $team->contains($student)));
                $project->people()->syncWithoutDetaching([$waiting->id => ['status' => ProjectMemberStatus::Pending->value, 'message' => 'I would love to help.']]);
            }
        }
    }

    /**
     * A clock time on campus (Beirut), stored in UTC like every other date, so "2 PM" shows as 2 PM on a Lebanese phone.
     */
    private function campusTime(int $days, int $hour): Carbon
    {
        return now('Asia/Beirut')->addDays($days)->setTime($hour, 0)->utc();
    }

    private function pick(Collection $items): mixed
    {
        return fake()->randomElement($items->values()->all());
    }

    private function pickMany(Collection $items, int $count): Collection
    {
        // Same collection class back (an Eloquent collection keeps modelKeys()).
        return $items::make(fake()->randomElements($items->values()->all(), $count));
    }
}
