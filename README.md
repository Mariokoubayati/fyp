# UniCircle

A mobile app that puts campus life for university students in one place: study groups, a campus-only marketplace, and peer-to-peer micro-lending with a trust score.

Final Year Project, Antonine University (Faculty of Engineering and Technology). Supervisor: Dr. Ahmad Kassem. Students: Ahmad Shmoury, Mario Koubayati.

## Stack

| Layer | Technology |
| --- | --- |
| Mobile app | React Native (Expo) |
| Backend API | Laravel 13 (PHP 8.3), JWT auth (`php-open-source-saver/jwt-auth`) |
| Database | MySQL |
| AI (trust model, recommender) | Python + Flask + scikit-learn (`ml/`) |

## Repository layout

```
backend/   Laravel REST API (/api/v1)
mobile/    Expo / React Native app
ml/        Python AI service: trust model, recommender, synthetic data, evaluation (see ml/README.md)
docs/      Build plan and supervisor responses
```

## Backend: run it locally

Requirements: PHP 8.3+, Composer, MySQL (XAMPP works).

```sh
cd backend
composer install
cp .env.example .env          # then set DB_* values to your MySQL
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve             # http://127.0.0.1:8000
```

Demo data (40 students, study groups, listings, loans, vouches; every password is `password`, demo login `202300000@ua.edu.lb`):

```sh
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Run the tests (uses in-memory SQLite, no MySQL needed):

```sh
cd backend
php artisan test
```

Run the same tests on MySQL (what CI does, catches strict-mode, enum and foreign-key differences):

```sh
cd backend
DB_CONNECTION=mysql DB_DATABASE=unicircle_test DB_USERNAME=root DB_PASSWORD= \
  php -d variables_order=EGPCS vendor/bin/phpunit
```

Check the whole running app end to end (start `php artisan serve` and seed `DemoSeeder` first; prints PASS/FAIL per check):

```sh
cd backend
php artisan app:e2e            # or --base=https://your-domain/api/v1
```

Test accounts from `DemoSeeder` (password `password`): `202300000@ua.edu.lb` (Test User), `202300001` and `202300002` (same courses, for two-student flows), `202300009` (unverified). The mobile app also runs in a browser (`npx expo start`, then `w`) so a second student can be played without a second phone. The full manual checklist is `docs/TEST-PLAN.md`.

CI (`.github/workflows/ci.yml`) runs the backend suite on MySQL 8 and SQLite, the mobile typecheck and lint, and the ML tests, on every pull request.

## API (v1)

Base path: `/api/v1`. Authenticated routes need `Authorization: Bearer <token>`.

| Method | Path | Auth | Description |
| --- | --- | --- | --- |
| POST | `/auth/register` | no | Create an account (university email only, `@ua.edu.lb`), returns a token and sends a verification email |
| POST | `/auth/login` | no | Exchange credentials for a token (5 attempts per minute) |
| POST | `/auth/logout` | yes | Invalidate the current token |
| POST | `/auth/refresh` | yes | Swap the token for a fresh one |
| GET | `/auth/email/verify/{id}/{hash}` | signed link | Confirm the email address from the verification mail |
| POST | `/auth/email/resend` | yes | Send the verification email again |
| GET | `/me` | yes | The authenticated user |
| PATCH | `/me` | yes | Update name, major, study year and bio |
| GET | `/courses` | yes | Course catalogue (`?search=`, paginated) |
| GET | `/me/courses` | yes | Courses the student is enrolled in |
| PUT | `/me/courses/{course}` | yes | Enroll in a course (idempotent) |
| DELETE | `/me/courses/{course}` | yes | Leave a course (idempotent) |
| GET | `/study-groups` | yes | Upcoming groups (`?course_id=`, `?mine=1`, paginated) |
| GET | `/study-groups/recommended` | yes | Open groups for you (recommender, or your courses by classmates as a fallback) |
| POST | `/study-groups` | verified | Start a group (you must be enrolled in the course) |
| GET | `/study-groups/{id}` | yes | One group |
| PATCH | `/study-groups/{id}` | creator | Edit a group |
| DELETE | `/study-groups/{id}` | creator | Cancel a group |
| PUT | `/study-groups/{id}/membership` | verified | Join (409 when full or already started) |
| DELETE | `/study-groups/{id}/membership` | yes | Leave (the creator cannot leave) |
| GET | `/me/trust` | yes | Your score, tier, component breakdown, flags and history |
| GET | `/recommendations` | yes | Home feed: 5 study groups and 5 listings picked for you, plus `source` (`model` or `rules`) |
| GET | `/conversations` | yes | Your chats, latest first, with preview and unread count |
| POST | `/conversations` | verified | Open the chat with a student (`user_id`); returns the existing one if any |
| GET | `/conversations/{id}/messages` | yes | Latest 50 messages, or only those after `?after=<id>` (the app polls every 4 s); marks them read |
| POST | `/conversations/{id}/messages` | yes | Send a message (`body`, max 2000 chars, 30 per minute) |
| PUT / DELETE | `/users/{id}/block` | yes | Block or unblock a student (no messages either way) |
| POST | `/users/{id}/report` | yes | Report a student (`reason`: harassment, spam, scam, inappropriate, other) |
| GET | `/notifications` | yes | Latest 50 notifications and the unread count |
| POST | `/notifications/read` | yes | Mark all, or the given `ids`, as read |
| GET | `/me/badges` | yes | Unread messages and notifications for the header badges |
| GET | `/users/{id}/trust` | yes | Another student's `score`, `tier`, `max_loan` |
| GET | `/users?search=` | yes | Find students by name (id, name, major only) |
| GET | `/loans` | yes | Open requests from others; `?role=borrowed` or `?role=lent` for your own |
| POST | `/loans` | verified | Request a loan (capped by your trust tier, $5-$100, due within 60 days) |
| GET | `/loans/{id}` | party / open | One loan |
| POST | `/loans/{id}/accept` | verified | Fund an open request |
| POST | `/loans/{id}/confirm-handover` | party | Confirm the cash changed hands (loan goes active when both did) |
| POST | `/loans/{id}/confirm-repayment` | party | Confirm repayment (loan closes when both did; writes a trust event) |
| POST | `/loans/{id}/cancel` | party | Withdraw a request or drop an accepted loan |
| GET | `/users/{id}` | yes | A student's public profile with trust summary and vouch state |
| PUT/DELETE | `/users/{id}/vouch` | verified / yes | Vouch for a classmate or take it back (max 10 given) |
| POST | `/listings/{id}/rating` | buyer | Rate the seller 1-5 after a sale (feeds their trust score) |
| GET | `/listings` | yes | Available listings (`?search=`, `?category=`, `?course_id=`, `?max_price=`) or `?mine=1` |
| POST | `/listings` | verified | Put an item up for sale |
| GET | `/listings/{id}` | yes | One listing |
| PATCH | `/listings/{id}` | seller | Edit; status may be `available` or `reserved` |
| DELETE | `/listings/{id}` | seller | Take it down |
| POST | `/listings/{id}/sold` | seller | Close the sale, body `buyer_id` |

Routes marked *verified* need a confirmed university email (403 otherwise). Success responses are wrapped in `data`. Errors return `message` (and `errors` for validation failures).

## Lending and trust

UniCircle is a ledger: no money moves through the app. A loan goes `requested -> accepted -> active -> repaid`,
or `active -> overdue -> defaulted` (and still `-> repaid` late). All transitions go through `LoanStateMachine`.
`php artisan loans:process-due` (scheduled daily) moves unpaid loans to overdue/defaulted and writes trust events.

The trust score is rule-based and explainable (`config/trust.php`): baseline 500 plus three capped components
(repayment 60%, marketplace 25%, vouches 15% of a +/-500 range), clamped to 0-1000.
Tiers: below 400 no borrowing, 400-699 up to $20, 700+ up to $100. A new verified student starts at 500 (basic).

Anti-collusion rules, because no money moves and friends could fake activity:

| Rule | Effect |
| --- | --- |
| Same-pair cap | Only 2 rewards per partner per component in any 90 days |
| Minimum loan duration | A loan repaid within 48h of handover is logged but earns nothing |
| Lender diversity | Repayment rewards x min(1, different lenders / 3) |
| Vouch rules | Vouches from unverified or sub-400 students count 0; mutual vouches count half |
| Circular lending | A->B->A or A->B->C->A within 90 days holds the tier at basic (flag `circular_lending`) |
| New account | Accounts under 14 days old cannot reach the top tier (flag `new_account`) |

Penalties are never capped. `tests/Feature/Services/Trust/CollusionAttackTest.php` runs the attacks through the API.

**Messaging.** Message bodies are encrypted at rest with Laravel's `encrypted` cast (AES-256 with the app key), and traffic is encrypted in transit over HTTPS when deployed. There is no end-to-end encryption. A block stops messages both ways. Unreviewed reports from 3 different students stop a student from starting new chats (replies still work) until `reviewed_at` is set on the reports. In-app notifications cover new messages (one per conversation until read), accepted loans, loans due in 2 days and overdue loans.

**AI risk check.** When `TRUST_MODEL_ENABLED=true`, Laravel sends the student's history to the Flask service, which
predicts the default risk for each loan size the rules allow. The tier drops to the largest size with risk under 30%.
The model can only lower a tier, the displayed score stays the rule score, and if the service is down the rules decide
alone. Results against the rule baseline: [`ml/reports/RESULTS.md`](ml/reports/RESULTS.md).

## Branch and review rules

Work on a feature branch (`feature/<name>`) and merge into `main` through a pull request reviewed by the other student.
