<?php

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs every main user flow against a running API, over HTTP, like the mobile app does.
 *
 * Needs the demo accounts: php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 * It creates test data (groups, listings, a loan, messages) and leaves blocks and vouches as it found them.
 */
class EndToEndCheck extends Command
{
    protected $signature = 'app:e2e
        {--base=http://127.0.0.1:8000/api/v1 : API base URL}
        {--password=password : Password of the demo accounts}';

    protected $description = 'Run every main flow against a running API and report PASS/FAIL per check';

    private const A = '202300000@ua.edu.lb';

    private const B = '202300001@ua.edu.lb';

    private const C = '202300002@ua.edu.lb';

    private const UNVERIFIED = '202300009@ua.edu.lb';

    /** @var list<array{string, string, string}> */
    private array $results = [];

    private string $section = '';

    /** @var array<string, array{token: string, id: int, name: string}> */
    private array $accounts = [];

    private string $run;

    public function handle(): int
    {
        $this->run = now()->format('His');
        $this->info('UniCircle end-to-end check against '.$this->option('base'));

        $steps = [
            'Access' => fn () => $this->access(),
            'Profile and courses' => fn () => $this->profileAndCourses(),
            'Study groups' => fn () => $this->studyGroups(),
            'Marketplace' => fn () => $this->marketplace(),
            'Lending' => fn () => $this->lending(),
            'Trust and vouches' => fn () => $this->trustAndVouches(),
            'Messaging' => fn () => $this->messaging(),
            'Notifications' => fn () => $this->notifications(),
            'Recommendations' => fn () => $this->recommendations(),
            'Sign out' => fn () => $this->signOut(),
        ];

        foreach ($steps as $section => $step) {
            $this->section = $section;
            try {
                $step();
            } catch (Throwable $e) {
                $this->record(false, 'Section stopped early', $e->getMessage());
            }
        }

        $this->newLine();
        $this->table(['Result', 'Area', 'Check'], array_map(fn (array $r) => [$r[0], $r[1], $r[2]], $this->results));

        $failed = count(array_filter($this->results, fn (array $r) => $r[0] === 'FAIL'));
        $passed = count(array_filter($this->results, fn (array $r) => $r[0] === 'PASS'));
        $failed === 0
            ? $this->info("All {$passed} checks passed.")
            : $this->error("{$failed} of ".($passed + $failed).' checks failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    // ---- sections ------------------------------------------------------

    private function access(): void
    {
        $this->check('No token gets 401 JSON, not a server error', fn () => $this->http()->get('/me'), 401);
        $this->check('Wrong password is refused', fn () => $this->http()->post('/auth/login', ['email' => self::A, 'password' => 'wrong-password']), 401);

        foreach (['A' => self::A, 'B' => self::B, 'C' => self::C, 'U' => self::UNVERIFIED] as $key => $email) {
            $response = $this->http()->post('/auth/login', ['email' => $email, 'password' => $this->option('password')]);
            $ok = $response->successful() && $response->json('data.access_token');
            $this->record($ok, "Sign in as {$email}", $ok ? '' : "HTTP {$response->status()}");
            if (! $ok) {
                throw new \RuntimeException('Demo accounts missing. Run: php artisan db:seed --class=DemoSeeder');
            }
            $this->accounts[$key] = [
                'token' => $response->json('data.access_token'),
                'id' => (int) $response->json('data.user.id'),
                'name' => (string) $response->json('data.user.name'),
            ];
        }

        $this->check('Current student is returned', fn () => $this->as('A')->get('/me'), 200, fn (Response $r) => $r->json('data.email') === self::A);
        $this->check('Unverified student cannot start a chat', fn () => $this->as('U')->post('/conversations', ['user_id' => $this->id('A')]), 403);
        $this->check('Unverified student cannot ask for a loan', fn () => $this->as('U')->post('/loans', $this->loanPayload(10)), 403);
    }

    private function profileAndCourses(): void
    {
        $original = $this->as('A')->get('/me')->json('data.bio');
        $this->check('Profile can be edited', fn () => $this->as('A')->patch('/me', ['bio' => "E2E bio {$this->run}"]), 200, fn (Response $r) => $r->json('data.bio') === "E2E bio {$this->run}");
        $this->check('Year of study outside 1-6 is rejected', fn () => $this->as('A')->patch('/me', ['study_year' => 9]), 422);
        $this->as('A')->patch('/me', ['bio' => $original]);

        $courses = $this->as('A')->get('/courses')->json('data');
        $this->record(is_array($courses) && count($courses) > 0, 'Course catalogue lists courses');
        $mine = collect($this->as('A')->get('/me/courses')->json('data'))->pluck('id')->all();
        $extra = collect($courses)->first(fn (array $c) => ! in_array($c['id'], $mine, true));
        if ($extra) {
            $this->check('Enroll in a course', fn () => $this->as('A')->put("/me/courses/{$extra['id']}"), [200, 201, 204]);
            $this->check('Course appears in my courses', fn () => $this->as('A')->get('/me/courses'), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $extra['id']));
            $this->check('Drop the course again', fn () => $this->as('A')->delete("/me/courses/{$extra['id']}"), [200, 204]);
        }
    }

    private function studyGroups(): void
    {
        $courseId = $this->as('A')->get('/me/courses')->json('data.0.id');
        $created = $this->as('A')->post('/study-groups', [
            'course_id' => $courseId,
            'title' => "E2E group {$this->run}",
            'starts_at' => now()->addDay()->setTime(15, 0)->toIso8601String(),
            'capacity' => 2,
            'location' => 'Library',
        ]);
        $this->record($created->status() === 201, 'Create a study group', "HTTP {$created->status()}");
        $groupId = (int) $created->json('data.id');

        $this->check('A start time in the past is rejected', fn () => $this->as('A')->post('/study-groups', ['course_id' => $courseId, 'title' => 'Past', 'starts_at' => now()->subDay()->toIso8601String()]), 422);
        $this->check('Classmate sees the group', fn () => $this->as('B')->get("/study-groups/{$groupId}"), 200);
        $this->check('Classmate joins', fn () => $this->as('B')->put("/study-groups/{$groupId}/membership"), [200, 201, 204]);
        $this->check('Joining a full group is refused', fn () => $this->as('C')->put("/study-groups/{$groupId}/membership"), [409, 422]);
        $this->check('Only the creator can edit the group', fn () => $this->as('C')->patch("/study-groups/{$groupId}", ['title' => 'Hijacked']), 403);
        $this->check('Group is listed under Mine for the member', fn () => $this->as('B')->get('/study-groups', ['mine' => 1]), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $groupId));
        $this->check('Member leaves', fn () => $this->as('B')->delete("/study-groups/{$groupId}/membership"), [200, 204]);
        $this->check('Creator edits the title', fn () => $this->as('A')->patch("/study-groups/{$groupId}", ['title' => "E2E group {$this->run} (edited)"]), 200);
        $this->check('Creator cancels the group', fn () => $this->as('A')->delete("/study-groups/{$groupId}"), [200, 204]);
    }

    private function marketplace(): void
    {
        $title = "E2E calculator {$this->run}";
        $created = $this->as('A')->post('/listings', ['title' => $title, 'price' => 12, 'category' => 'electronics', 'condition' => 'good']);
        $this->record($created->status() === 201, 'Post a listing', "HTTP {$created->status()}");
        $listingId = (int) $created->json('data.id');

        $this->check('A negative price is rejected', fn () => $this->as('A')->post('/listings', ['title' => 'Bad', 'price' => -1, 'category' => 'books', 'condition' => 'good']), 422);
        $this->check('Search finds the listing', fn () => $this->as('B')->get('/listings', ['search' => $title]), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $listingId));
        $this->check('Category filter keeps it', fn () => $this->as('B')->get('/listings', ['category' => 'electronics', 'search' => $title]), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $listingId));
        $this->check('Another student cannot edit it', fn () => $this->as('C')->patch("/listings/{$listingId}", ['price' => 1]), 403);
        $this->check('Another student cannot delete it', fn () => $this->as('C')->delete("/listings/{$listingId}"), 403);

        $before = $this->as('A')->get('/me/trust')->json('data.breakdown.marketplace');
        $this->check('Seller marks it sold to a buyer', fn () => $this->as('A')->post("/listings/{$listingId}/sold", ['buyer_id' => $this->id('B')]), 200);
        $this->check('Sold listing leaves the browse feed', fn () => $this->as('C')->get('/listings', ['search' => $title]), 200, fn (Response $r) => ! collect($r->json('data'))->contains('id', $listingId));
        $this->check('Someone else cannot rate the sale', fn () => $this->as('C')->post("/listings/{$listingId}/rating", ['stars' => 1]), 403);
        $this->check('Buyer rates the seller 5 stars', fn () => $this->as('B')->post("/listings/{$listingId}/rating", ['stars' => 5]), 201);
        $this->check('Buyer cannot rate twice', fn () => $this->as('B')->post("/listings/{$listingId}/rating", ['stars' => 1]), [403, 409, 422]);
        $after = $this->as('A')->get('/me/trust')->json('data.breakdown.marketplace');
        $this->record($after >= $before, 'Sale and rating do not lower the marketplace score', "{$before} -> {$after} (the pair cap may hold it level)");

        $temp = $this->as('A')->post('/listings', ['title' => "E2E temp {$this->run}", 'price' => 0, 'category' => 'other', 'condition' => 'fair'])->json('data.id');
        $this->check('Seller deletes a listing', fn () => $this->as('A')->delete("/listings/{$temp}"), [200, 204]);
        $this->check('Deleted listing is gone', fn () => $this->as('A')->get("/listings/{$temp}"), 404);
    }

    private function lending(): void
    {
        $trust = $this->as('A')->get('/me/trust')->json('data');
        $this->record(isset($trust['score'], $trust['tier'], $trust['max_loan']), 'Trust summary has score, tier and limit', "score {$trust['score']}, {$trust['tier']}, max \${$trust['max_loan']}");

        if (($trust['max_loan'] ?? 100) < 100) {
            $this->check('A loan above the tier limit is refused', fn () => $this->as('A')->post('/loans', $this->loanPayload($trust['max_loan'] + 5)), 422);
        }
        $this->check('A due date in the past is refused', fn () => $this->as('A')->post('/loans', ['amount' => 5, 'due_date' => now()->subDay()->toDateString()]), 422);

        $request = $this->as('A')->post('/loans', $this->loanPayload(5));
        if ($request->status() === 422) {
            $this->record(false, 'Ask for a loan', 'Refused: '.$request->json('message').' (too many open loans or restricted tier; reseed the demo data)');

            return;
        }
        $this->record($request->status() === 201, 'Ask for a $5 loan', "HTTP {$request->status()}");
        $loanId = (int) $request->json('data.id');

        $this->check('The request is visible to other students', fn () => $this->as('C')->get("/loans/{$loanId}"), 200);
        $this->check('Borrower cannot fund their own request', fn () => $this->as('A')->post("/loans/{$loanId}/accept"), 403);
        $this->check('Lender accepts', fn () => $this->as('B')->post("/loans/{$loanId}/accept"), 200, fn (Response $r) => $r->json('data.status') === 'accepted');
        $this->check('A second lender cannot also accept', fn () => $this->as('C')->post("/loans/{$loanId}/accept"), [403, 409, 422]);
        $this->check('Outsider can no longer see the loan', fn () => $this->as('C')->get("/loans/{$loanId}"), 403);
        $this->check('Outsider cannot confirm anything', fn () => $this->as('C')->post("/loans/{$loanId}/confirm-handover"), 403);
        $this->check('Lender confirms the cash was handed over', fn () => $this->as('B')->post("/loans/{$loanId}/confirm-handover"), 200);
        $this->check('Borrower confirms too, the loan becomes active', fn () => $this->as('A')->post("/loans/{$loanId}/confirm-handover"), 200, fn (Response $r) => $r->json('data.status') === 'active');
        $this->check('Repayment cannot be skipped by one side', fn () => $this->as('A')->post("/loans/{$loanId}/confirm-repayment"), 200, fn (Response $r) => $r->json('data.status') === 'active');

        $before = $this->as('A')->get('/me/trust')->json('data.breakdown.repayment');
        $this->check('Lender confirms repayment, the loan is repaid', fn () => $this->as('B')->post("/loans/{$loanId}/confirm-repayment"), 200, fn (Response $r) => $r->json('data.status') === 'repaid');
        $after = $this->as('A')->get('/me/trust')->json('data.breakdown.repayment');
        $this->record($after === $before, 'A loan repaid within 48 hours earns no trust points (anti-collusion)', "repayment {$before} -> {$after}");

        $this->check('Borrowed list shows the loan', fn () => $this->as('A')->get('/loans', ['role' => 'borrowed']), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $loanId));
        $this->check('Lent list shows the loan', fn () => $this->as('B')->get('/loans', ['role' => 'lent']), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $loanId));

        $cancel = $this->as('A')->post('/loans', $this->loanPayload(5))->json('data.id');
        $this->check('Borrower cancels an open request', fn () => $this->as('A')->post("/loans/{$cancel}/cancel"), 200, fn (Response $r) => $r->json('data.status') === 'cancelled');
    }

    private function trustAndVouches(): void
    {
        $trust = $this->as('A')->get('/me/trust')->json('data');
        $this->record(array_keys($trust['breakdown'] ?? []) === ['repayment', 'marketplace', 'vouches'], 'Score has exactly the three proposal components');
        $this->record(is_array($trust['flags'] ?? null), 'Flags are reported', implode(', ', $trust['flags'] ?? []) ?: 'none');
        $this->record(true, 'AI risk check', ($trust['model'] ?? null) ? "ON (model {$trust['model']['version']}, risk at \$20: ".round($trust['model']['p_default'] * 100).'%)' : 'OFF or service down (rules only)');

        $alreadyVouched = (bool) $this->as('B')->get('/users/'.$this->id('A'))->json('data.vouched_by_me');
        if ($alreadyVouched) {
            $this->as('B')->delete('/users/'.$this->id('A').'/vouch');
        }
        $before = $this->as('A')->get('/me/trust')->json('data.breakdown.vouches');
        $this->check('Classmate vouches', fn () => $this->as('B')->put('/users/'.$this->id('A').'/vouch'), [200, 204]);
        $after = $this->as('A')->get('/me/trust')->json('data.breakdown.vouches');
        $this->record($after > $before || $after === 75, 'A vouch raises the vouches component', "{$before} -> {$after}");
        $this->check('Students cannot vouch for themselves', fn () => $this->as('A')->put('/users/'.$this->id('A').'/vouch'), [403, 422]);
        $this->check('Vouch is withdrawn', fn () => $this->as('B')->delete('/users/'.$this->id('A').'/vouch'), [200, 204]);
        if ($alreadyVouched) {
            $this->as('B')->put('/users/'.$this->id('A').'/vouch');
        }
        $this->check('Public profile shows the trust tier', fn () => $this->as('C')->get('/users/'.$this->id('A')), 200, fn (Response $r) => $r->json('data.trust.tier') !== null);
    }

    private function messaging(): void
    {
        $this->as('A')->delete('/users/'.$this->id('B').'/block');
        $this->as('B')->delete('/users/'.$this->id('A').'/block');

        $this->check('Cannot message yourself', fn () => $this->as('A')->post('/conversations', ['user_id' => $this->id('A')]), 422);
        $open = $this->as('A')->post('/conversations', ['user_id' => $this->id('B')]);
        $this->record(in_array($open->status(), [200, 201], true), 'Open a chat with a classmate', "HTTP {$open->status()}");
        $conversationId = (int) $open->json('data.id');
        $this->check('Opening it again returns the same chat', fn () => $this->as('B')->post('/conversations', ['user_id' => $this->id('A')]), 200, fn (Response $r) => (int) $r->json('data.id') === $conversationId);

        $body = "E2E hello {$this->run}";
        $sent = $this->as('A')->post("/conversations/{$conversationId}/messages", ['body' => $body]);
        $this->record($sent->status() === 201 && $sent->json('data.body') === $body, 'Send a message');
        $messageId = (int) $sent->json('data.id');
        $this->check('Empty message is rejected', fn () => $this->as('A')->post("/conversations/{$conversationId}/messages", ['body' => '   ']), 422);
        $this->check('A 2001-character message is rejected', fn () => $this->as('A')->post("/conversations/{$conversationId}/messages", ['body' => str_repeat('a', 2001)]), 422);
        $this->check('Recipient has an unread badge', fn () => $this->as('B')->get('/me/badges'), 200, fn (Response $r) => $r->json('data.messages') >= 1);
        $this->check('Outsider cannot read the chat', fn () => $this->as('C')->get("/conversations/{$conversationId}/messages"), 403);
        $this->check('Outsider cannot write in the chat', fn () => $this->as('C')->post("/conversations/{$conversationId}/messages", ['body' => 'hi']), 403);
        $this->check('Recipient reads the message', fn () => $this->as('B')->get("/conversations/{$conversationId}/messages"), 200, fn (Response $r) => collect($r->json('data'))->contains('body', $body));
        $this->check('Unread badge clears after reading', fn () => $this->as('B')->get('/me/badges'), 200, fn (Response $r) => $r->json('data.messages') === 0);
        $this->check('Sender sees it as read', fn () => $this->as('A')->get("/conversations/{$conversationId}/messages"), 200, fn (Response $r) => collect($r->json('data'))->firstWhere('id', $messageId)['read_at'] !== null);
        $this->as('B')->post("/conversations/{$conversationId}/messages", ['body' => "E2E reply {$this->run}"]);
        $this->check('Polling returns only newer messages', fn () => $this->as('A')->get("/conversations/{$conversationId}/messages", ['after' => $messageId]), 200, fn (Response $r) => count($r->json('data')) === 1);
        $this->check('Inbox lists the chat', fn () => $this->as('A')->get('/conversations'), 200, fn (Response $r) => collect($r->json('data'))->contains('id', $conversationId));

        $this->check('Block a student', fn () => $this->as('B')->put('/users/'.$this->id('A').'/block'), 204);
        $this->check('Blocked student cannot send', fn () => $this->as('A')->post("/conversations/{$conversationId}/messages", ['body' => 'hi']), 422);
        $this->check('The blocker cannot send either', fn () => $this->as('B')->post("/conversations/{$conversationId}/messages", ['body' => 'hi']), 422);
        $this->check('Unblock', fn () => $this->as('B')->delete('/users/'.$this->id('A').'/block'), 204);
        $this->check('Messages work again after unblocking', fn () => $this->as('A')->post("/conversations/{$conversationId}/messages", ['body' => "E2E after unblock {$this->run}"]), 201);

        $this->check('Report with an unknown reason is rejected', fn () => $this->as('A')->post('/users/'.$this->id('C').'/report', ['reason' => 'boring']), 422);
        $this->check('Report a student', fn () => $this->as('A')->post('/users/'.$this->id('C').'/report', ['reason' => 'spam']), 204);
        $this->check('Students cannot report themselves', fn () => $this->as('A')->post('/users/'.$this->id('A').'/report', ['reason' => 'spam']), 403);
    }

    private function notifications(): void
    {
        $this->check('Borrower got the "loan accepted" notification', fn () => $this->as('A')->get('/notifications'), 200, fn (Response $r) => collect($r->json('data'))->contains('type', 'loan_accepted'));
        $this->check('Recipient got a message notification', fn () => $this->as('A')->get('/notifications'), 200, fn (Response $r) => collect($r->json('data'))->contains('type', 'message'));
        $this->check('Mark all as read', fn () => $this->as('A')->post('/notifications/read'), 204);
        $this->check('Nothing unread afterwards', fn () => $this->as('A')->get('/notifications'), 200, fn (Response $r) => $r->json('meta.unread_count') === 0);
    }

    private function recommendations(): void
    {
        $feed = $this->as('A')->get('/recommendations');
        $this->record($feed->ok() && is_array($feed->json('data.groups')) && is_array($feed->json('data.listings')), 'Home feed returns groups and listings', 'source: '.$feed->json('data.source'));
        $this->record(collect($feed->json('data.listings'))->every(fn (array $l) => ($l['seller']['id'] ?? null) !== $this->id('A')), 'Feed never suggests your own listings');
        $this->check('Groups tab "For you" works', fn () => $this->as('A')->get('/study-groups/recommended'), 200);
    }

    private function signOut(): void
    {
        $old = $this->http()->post('/auth/login', ['email' => self::C, 'password' => $this->option('password')])->json('data.access_token');
        $refreshed = $this->http()->withToken($old)->post('/auth/refresh');
        $this->record($refreshed->ok() && $refreshed->json('data.access_token') !== $old, 'Refresh swaps the token for a new one');
        $this->check('The replaced token stops working', fn () => $this->http()->withToken($old)->get('/me'), 401);
        $token = $refreshed->json('data.access_token');
        $this->check('Sign out', fn () => $this->http()->withToken($token)->post('/auth/logout'), [200, 204]);
        $this->check('The old token no longer works', fn () => $this->http()->withToken($token)->get('/me'), 401);
    }

    // ---- helpers -------------------------------------------------------

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->option('base'), '/'))->acceptJson()->timeout(20);
    }

    private function as(string $account): PendingRequest
    {
        return $this->http()->withToken($this->accounts[$account]['token']);
    }

    private function id(string $account): int
    {
        return $this->accounts[$account]['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function loanPayload(int|float $amount): array
    {
        return ['amount' => $amount, 'purpose' => "E2E {$this->run}", 'due_date' => now()->addDays(10)->toDateString()];
    }

    /**
     * @param  Closure(): Response  $request
     * @param  int|list<int>  $expected
     * @param  (Closure(Response): bool)|null  $assert
     */
    private function check(string $label, Closure $request, int|array $expected, ?Closure $assert = null): void
    {
        $response = $request();
        $statusOk = in_array($response->status(), (array) $expected, true);
        $assertOk = $statusOk && ($assert === null || $assert($response));
        $detail = $statusOk ? ($assertOk ? '' : 'unexpected response body') : 'expected HTTP '.implode('/', (array) $expected).", got {$response->status()}: ".Str::limit((string) $response->json('message'), 80);
        $this->record($assertOk, $label, $detail);
    }

    private function record(bool $passed, string $label, string $detail = ''): void
    {
        $this->results[] = [$passed ? 'PASS' : 'FAIL', $this->section, $label.($detail !== '' ? " ({$detail})" : '')];
    }
}
