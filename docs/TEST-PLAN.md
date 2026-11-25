# UniCircle: Full Test Plan

**Version:** 2026-11-24 · **Covers:** PRs #1 to #8: everything built so far, including the event and project hub

No test plan can prove software is 100% correct. This one makes sure that **every feature, every rule and every error case has been checked at least once, with evidence**, in three layers:

| Layer | What it checks | How long | Who | When |
|---|---|---|---|---|
| **1. Automated tests** | Every API rule, permission and calculation (360 backend tests, 38 ML tests, app type-check and lint) | runs by itself | GitHub CI | Every pull request |
| **2. End-to-end script** | All main flows over HTTP against your running server (135 checks) | ~1 minute | You, one command | After setup, after every merge, before a demo |
| **3. Manual phone checklist** | What only a person can see: screens, taps, keyboard, two phones talking, offline | ~3 hours per platform | You | Once on iPhone, once on Android, then the "smoke" subset after changes |

If layer 1 or 2 fails, fix that first. There is no point testing screens on a broken server.

---

## 0. Setup (once)

### 0.1 Backend on your laptop

```sh
cd backend
composer install
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
php artisan serve --host=0.0.0.0 --port=8000
```

`--host=0.0.0.0` is required. Without it your phone cannot reach the laptop.

### 0.2 Find your laptop's address

- **Mac:** System Settings → Wi-Fi → Details → IP address (for example `192.168.1.23`)
- **Windows:** `ipconfig` → "IPv4 Address"

Laptop and phones must be on the **same Wi-Fi**. University Wi-Fi often blocks phone-to-laptop traffic; a phone hotspot works.

### 0.3 Mobile app

In `mobile/.env`:

```
EXPO_PUBLIC_API_URL=http://192.168.1.23:8000/api/v1
```

Then:

```sh
cd mobile
npm install
npx expo start
```

- **iPhone:** scan the QR code with the Camera app (needs Expo Go from the App Store)
- **Android:** scan the QR code from inside Expo Go (Play Store)
- **Browser (second student):** press `w` in the same terminal. The browser build is only for playing the second student; test real screens on the phones.

### 0.4 AI service (for the AI cases only)

```sh
cd ml
pip install -r requirements.txt
python -m unicircle_ml.service
```

In `backend/.env`: `TRUST_MODEL_ENABLED=true` and `RECOMMENDER_ENABLED=true`, then restart `php artisan serve`.

### 0.5 Test accounts (password for all: `password`)

| Account | Email | Use it as |
|---|---|---|
| **A** | `202300000@ua.edu.lb` | You, the main tester (Test User) |
| **B** | `202300001@ua.edu.lb` | The other student: lender, buyer, chat partner (Test User Two) |
| **C** | `202300002@ua.edu.lb` | An outsider, for "should not be allowed" cases (Test User Three) |
| **U** | `202300009@ua.edu.lb` | Unverified student |

A, B and C share the same courses. Demo times are set in Beirut time (a 2 PM workshop shows 2 PM on the phone). 40 other demo students exist with random names. Interests and skills: A = Tech, Career / Laravel, SQL; B = Tech, Sports / Python, UI design; C = Arts, Social / Photography. The demo data also has 16 upcoming events (3 past) and 8 projects, the same on every reset. Badges: A starts with **First sale** and is one group short of **Study buddy** (2/3), so joining a study group earns it on the spot.

### 0.6 Reset to a clean state

Before a full pass, and whenever data gets confusing:

```sh
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
```

---

## 1. Layer 1: automated tests

```sh
cd backend && php artisan test          # 360 tests
cd ml && python -m pytest -q            # 38 tests
cd mobile && npm run typecheck && npx eslint .
```

On GitHub, every pull request runs all of this, with the backend tests on **both MySQL 8 and SQLite**. A green check on the PR means layer 1 passed.

**What layer 1 covers** (evidence for the report):

| Area | Test files (tests) |
|---|---|
| Sign-up, login, tokens, email verification | Register (10), Login (5), Logout (2), Refresh (5), VerifyEmail (5), ResendVerification (3), Unauthenticated API (2) |
| Profile, courses, search | CurrentUser (3), UpdateProfile (8: incl. interests and skills), Course index (3), Enrollment (10), UserSearch (4), StudentProfile (3) |
| Study groups | Store (5), Index (5), Show (3), Update (4), Destroy (3), Join (7), Leave (4), Recommended (4) |
| Marketplace | Store (5), Index (6), Show (4), Update (6), Destroy (3), MarkSold (7), Rating (7) |
| Lending | Store (10), Index (4), Show (4), Accept (5), ConfirmHandover (5), ConfirmRepayment (7), Cancel (5), State machine (2), Due loans job (5) |
| Trust score | Calculator (19), Service (4), Collusion attacks through the API (5), AI gate (8), MyTrust (2), UserTrust (3), Vouch (10), Recalculate (1), Verified listener (1) |
| Events | Store (5), Index (4), Show (2), Update (4), Destroy (2), RSVP (5), Cancel RSVP (2), Recommended (2), Reminder command (1) |
| Projects | Store (3), Index (2), Show (1), Update (3), Destroy (1), Join request (3), Leave or withdraw (3), Accept or decline (3), Recommended (2) |
| Recommender + home feed | RecommendationController (9) |
| Messaging + notifications | Messaging (24), Review reports command (3), Loan notifications (3) |
| ML service | Features and rules parity (11), simulator (5), Flask API (5), recommender (17: groups, listings, events, projects) |

---

## 2. Layer 2: end-to-end script

With the server running (step 0.1) and the demo data seeded:

```sh
cd backend
php artisan app:e2e
```

It signs in as A, B, C and U and runs 135 checks over HTTP: access and permissions, profile, interests and courses, the whole study group flow, events (post, topic filter, RSVP, full, cancel), projects (post, request, accept, full team, notification), the whole marketplace flow, a full loan from request to repayment, trust and vouches, messaging with block and report, notifications, My activity and badges, the home feed, token refresh and sign-out. It prints PASS/FAIL per check and ends with `All 135 checks passed.`

Against a deployed server: `php artisan app:e2e --base=https://your-domain/api/v1`.

It creates test data (groups, listings, a loan, messages). Reset (0.6) before a demo.

---

## 3. Layer 3: manual checklist (iPhone and Android)

**How to record:** tick ✅ when the result matches exactly, ❌ when it does not, and write a bug (section 5). "Smoke" cases (🔥) are the quick subset to rerun after every change (about 20 minutes).

### 3.1 Sign-up, sign-in, session (AUTH)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| AUTH-01 🔥 | Open the app fresh | Sign-in screen: "UniCircle", "Your campus, in one place.", email + password fields | ☐ | ☐ |
| AUTH-02 🔥 | Sign in as A | Home opens with "Hi, Test User" | ☐ | ☐ |
| AUTH-03 | Sign in as A with a wrong password | Error "Invalid email or password.", stays on sign-in | ☐ | ☐ |
| AUTH-04 | Wrong password 6 times within a minute | On the 6th try: "Too many attempts. Please wait a minute and try again." | ☐ | ☐ |
| AUTH-05 | Sign in with empty fields | Field errors under email and password, no crash | ☐ | ☐ |
| AUTH-06 | Create account → email `test1@gmail.com` | Refused: only `@ua.edu.lb` addresses | ☐ | ☐ |
| AUTH-07 | Create account → password `1234` | Refused: at least 8 characters | ☐ | ☐ |
| AUTH-08 | Create account → email `202300000@ua.edu.lb` | Refused: email already taken | ☐ | ☐ |
| AUTH-09 | Create account with a new `@ua.edu.lb` email (e.g. `202399999@ua.edu.lb`) | Account created and signed in; Profile shows "Verify your email" | ☐ | ☐ |
| AUTH-10 | Profile → "Resend verification email" | Confirmation message; the link appears in `backend/storage/logs/laravel.log` (local mail goes to the log) | ☐ | ☐ |
| AUTH-11 | Open the verification link from the log in the laptop browser, then reopen Profile on the phone | "Verify your email" box disappears | ☐ | ☐ |
| AUTH-12 | Sign in as U (unverified), try to ask for a loan and to message someone | Both refused with a clear message; no crash | ☐ | ☐ |
| AUTH-13 🔥 | Signed in as A, close the app fully (swipe away) and reopen | Still signed in, Home opens | ☐ | ☐ |
| AUTH-14 | Stay signed in for over an hour (or run the server with `JWT_TTL=1` and wait 2 minutes), then open Messages | Still signed in, data loads (the app renews the session by itself) | ☐ | ☐ |
| AUTH-15 🔥 | Profile → Sign out | Back to sign-in; reopening the app stays signed out | ☐ | ☐ |

### 3.2 Profile and courses (PROF)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| PROF-01 | Profile → Edit profile → change bio and major → Save | Values saved and shown on Profile | ☐ | ☐ |
| PROF-02 | Edit profile → Year of study `9` | Refused (1 to 6) | ☐ | ☐ |
| PROF-03 | Edit profile → Full name empty | Refused | ☐ | ☐ |
| PROF-04 | My courses → search "data" | Matching courses appear | ☐ | ☐ |
| PROF-05 | Add a course, then remove it | List updates each time; Home feed changes after adding | ☐ | ☐ |
| PROF-06 | Search a nonsense word | "No courses found" | ☐ | ☐ |
| PROF-07 | Edit profile → tap interests Arts and Sports, skills "Figma, Python" → Save | Back on Profile, the interests and skills show as small pills; Home suggestions change | ☐ | ☐ |
| PROF-08 | Skills "a" (one letter) or 11 skills | Refused with a message under the skills field | ☐ | ☐ |
| PROF-09 | As B, open A's public profile | A's interests and skills are shown (no email) | ☐ | ☐ |

### 3.3 Home feed (HOME)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| HOME-01 🔥 | As A, open Home | "Study groups for you", "For your courses", "Events for you" and "Projects looking for you" sections with cards; chat and bell icons top right | ☐ | ☐ |
| HOME-02 | Check the listings shown | None of them are A's own listings; mostly A's courses | ☐ | ☐ |
| HOME-03 | Tap a group card, then back; tap a listing card, then back | Opens the right detail screen each time | ☐ | ☐ |
| HOME-04 | Tap "See all" on each section | Opens Campus on Study groups / Events / Projects, or the Marketplace tab | ☐ | ☐ |
| HOME-05 | Sign in with a brand-new account that has no courses | "Nothing to suggest yet" + "Add my courses" button that opens My courses | ☐ | ☐ |
| HOME-06 | With the AI service running (0.4) | Greeting says "Picked for you from your courses, interests and the people you study with."; A's events are mostly Tech and Career, A's projects need Laravel | ☐ | ☐ |
| HOME-07 | Stop the AI service, pull Home again | Still works; greeting says "Study groups, items, events and projects for your courses and interests." | ☐ | ☐ |

### 3.4 Campus tab: study groups (GRP)

The tab is now called **Campus**, with a switcher at the top: Study groups / Events / Projects.

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| GRP-01 | Campus tab → Study groups → For you / All / Mine | Each filter loads; Mine shows only groups A belongs to | ☐ | ☐ |
| GRP-02 🔥 | Start a study group: A's course, tomorrow, capacity 2, location | Created; appears under Mine with "1/2 students" | ☐ | ☐ |
| GRP-03 | Create with an empty title | Refused | ☐ | ☐ |
| GRP-04 | Create with capacity 1 or 31 | Refused (2 to 30) | ☐ | ☐ |
| GRP-05 | Create with a time in the past | Refused | ☐ | ☐ |
| GRP-06 | Create with no courses enrolled (new account) | "Enroll in a course first" + "Choose my courses" | ☐ | ☐ |
| GRP-07 🔥 | As B (second device), open the group from GRP-02 → Join group | B is a member; "2/2 students · Full" | ☐ | ☐ |
| GRP-08 | As C, open the same group | Button says "Group is full" and is disabled | ☐ | ☐ |
| GRP-09 | As B → Leave group | B removed; count goes back to 1/2 | ☐ | ☐ |
| GRP-10 | As C, check there is no edit or cancel option on A's group | Only Join/Leave visible | ☐ | ☐ |
| GRP-11 | As A → Cancel this group | Group disappears from all lists | ☐ | ☐ |
| GRP-12 | Groups that already started | Never shown in For you or All | ☐ | ☐ |

### 3.5 Campus tab: events (EVT)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| EVT-01 🔥 | Campus → Events → For you / All / Going / Mine | Each filter loads; For you puts A's Tech and Career events first | ☐ | ☐ |
| EVT-02 | Tap the topic chips (Sports, then All topics) | Only Sports events, then everything again | ☐ | ☐ |
| EVT-03 🔥 | Post an event: title, where, tomorrow 6 PM, topic Tech, max people 2 | Opens the event: "1/2 going", "Cancel this event" | ☐ | ☐ |
| EVT-04 | Post with no topic, an empty place, or more than 3 topics | Refused (the picker stops at 3) | ☐ | ☐ |
| EVT-05 🔥 | As B, open the event → "I'm going" | "2/2 going · Full", note "We will remind you the day before" | ☐ | ☐ |
| EVT-06 | As C, open the full event | Button "Event is full", disabled | ☐ | ☐ |
| EVT-07 | As B → "I can't go anymore" | Back to 1/2 going | ☐ | ☐ |
| EVT-08 | As C, check there is no cancel option on A's event | Only "I'm going" | ☐ | ☐ |
| EVT-09 | Reminder: with B going to an event tomorrow, run `php artisan events:remind` twice | B gets exactly one "… is coming up" notification; tapping it opens the event | ☐ | ☐ |
| EVT-10 | As A → Cancel this event | Gone from every list; B gets "… was cancelled" | ☐ | ☐ |
| EVT-11 | Events that already started | Never in For you or All; opening one shows "This event has started" | ☐ | ☐ |
| EVT-12 | As A, open your event → Edit event → change the title, move it a day later, set 3 hours → Save changes | Back on the event with the new title and time, "… to …" 3 hours apart. Attendees stay | ☐ | ☐ |
| EVT-13 | Edit event with max people below the students going | Refused under "Max people" | ☐ | ☐ |
| EVT-14 | Reminder after a reschedule: B going to an event tomorrow, run `php artisan events:remind`, move the event 2 hours later, run it again | B gets a second "… is coming up" with the new time | ☐ | ☐ |
| EVT-15 | Tap an old notification of an event that was cancelled since | "This was removed or no longer exists.", no technical text | ☐ | ☐ |

### 3.6 Campus tab: projects (PRJ)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| PRJ-01 🔥 | Campus → Projects → For you / All / Joined / Mine | Each filter loads; For you puts projects needing A's skills (Laravel) first | ☐ | ☐ |
| PRJ-02 🔥 | As B, post a project: name, description, skills "Laravel, UI design", topic Tech, team size 3 | Opens it: "1/3 on the team", "Requests to join", "Stop looking for members" | ☐ | ☐ |
| PRJ-03 | Post with no skills, no topic, or team size 11 | Refused | ☐ | ☐ |
| PRJ-04 🔥 | As A, open it → write a message → Ask to join | "Request sent…" and "Withdraw my request"; B gets "Test User wants to join …" | ☐ | ☐ |
| PRJ-05 | As B, open the project | A's request with A's skills and message, Accept / Decline | ☐ | ☐ |
| PRJ-06 🔥 | As B → Accept | "2/3 on the team"; A gets "You are on the team for …"; tapping it opens the project with "Leave the team" | ☐ | ☐ |
| PRJ-07 | As C, ask to join, then B declines | C sees "The owner declined your request." and cannot ask again | ☐ | ☐ |
| PRJ-08 | Fill the team (team size reached) | Others see "Team is full", disabled | ☐ | ☐ |
| PRJ-09 | As B → Stop looking for members | Project leaves All and For you; "Look for members again" reopens it | ☐ | ☐ |
| PRJ-10 | As A → Leave the team | A is off the team, count goes down | ☐ | ☐ |
| PRJ-11 | As C, check there are no owner buttons on B's project and no requests list | Only Ask to join | ☐ | ☐ |
| PRJ-12 | As B → Edit project → change skills and team size → Save changes | Back on the project with the new skills and "x/new size on the team" | ☐ | ☐ |
| PRJ-13 | Edit project with team size below the members | Refused under "Team size" | ☐ | ☐ |
| PRJ-14 | Team size 2, A and C both ask; B accepts A | Team full, "No requests waiting"; C gets "The team for … is full" and can ask again if B raises the team size | ☐ | ☐ |
| PRJ-15 | As B → Delete project, with A on the team | A gets "… was deleted" | ☐ | ☐ |

### 3.7 Marketplace (MKT)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| MKT-01 | Marketplace → Browse; tap each category chip | List filters by category; "All" resets | ☐ | ☐ |
| MKT-02 | Search "notes" | Only matching listings | ☐ | ☐ |
| MKT-03 🔥 | Sell an item: title, price 12, Electronics, Good → Post listing | Appears under My listings and in Browse for B | ☐ | ☐ |
| MKT-04 | Post with price 0 | Allowed (free item) | ☐ | ☐ |
| MKT-05 | Post with empty title or negative price | Refused | ☐ | ☐ |
| MKT-06 | As B, open A's listing | "Sold by Test User" and "Message the seller"; no edit/delete | ☐ | ☐ |
| MKT-07 | As B, tap "Sold by Test User" | Opens A's public profile | ☐ | ☐ |
| MKT-08 🔥 | As A, open the listing → Mark as sold → pick B | Status "Sold"; disappears from Browse for everyone | ☐ | ☐ |
| MKT-09 | As B, open the sold listing → rate 5 | "You rated this seller 5/5. Thank you!" | ☐ | ☐ |
| MKT-10 | As C, open the sold listing | No rating option | ☐ | ☐ |
| MKT-11 | As A, post another listing → Delete listing | Gone from My listings and Browse | ☐ | ☐ |

### 3.8 Lending (LOAN)

Tier limits: Restricted $0, Basic $20, Trusted $100. A starts at Basic.

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| LOAN-01 | Lending tab → Requests / Borrowed / Lent | Each list loads; empty states read well | ☐ | ☐ |
| LOAN-02 | Ask for a loan screen | Shows "Trust tier: Basic. You can borrow up to $20." | ☐ | ☐ |
| LOAN-03 | Ask for $50 | Refused: above the tier limit | ☐ | ☐ |
| LOAN-04 | Ask for $3 (below $5) or with a due date in the past or more than 60 days away | Refused | ☐ | ☐ |
| LOAN-05 🔥 | Ask for $10, due in 10 days, purpose "Lunch" | Created; status "Looking for a lender"; listed under Borrowed | ☐ | ☐ |
| LOAN-06 | Ask for 2 more loans while the first is open | The third open loan is refused (max 2 open) | ☐ | ☐ |
| LOAN-07 | As A, open your own request | No "Fund this loan" button | ☐ | ☐ |
| LOAN-08 🔥 | As B, Lending → Requests → open A's loan → Fund this loan | Status "Waiting for handover"; A gets a "loan accepted" notification (bell badge) | ☐ | ☐ |
| LOAN-09 | As C, try to open that loan now | Not visible / refused | ☐ | ☐ |
| LOAN-10 | As B → "Confirm cash handed over" | Shows that B confirmed and A has not yet; status unchanged | ☐ | ☐ |
| LOAN-11 🔥 | As A → "Confirm cash handed over" | Status "Active" | ☐ | ☐ |
| LOAN-12 | As A → "Confirm repayment" | Still Active, waiting for B | ☐ | ☐ |
| LOAN-13 🔥 | As B → "Confirm repayment" | Status "Repaid"; appears under B's Lent and A's Borrowed | ☐ | ☐ |
| LOAN-14 | Check A's trust card after LOAN-13 | Repayment points did **not** go up (repaid within 48 hours: anti-collusion rule) | ☐ | ☐ |
| LOAN-15 | As A, ask for a loan, then Cancel request | Status "Cancelled" | ☐ | ☐ |
| LOAN-16 | As B, accept an A loan, then "Back out" before handover | Loan status "Cancelled" for both students (the borrower asks again if needed) | ☐ | ☐ |
| LOAN-17 | Overdue: make an active loan late (commands below), run the daily job | Status "Overdue" in red; A gets an "overdue" notification; repayment points drop by 60 | ☐ | ☐ |
| LOAN-18 | Due-soon reminder: set an active loan's due date to 2 days from now, run the job twice | Exactly **one** "due soon" notification | ☐ | ☐ |
| LOAN-19 | Default: make an overdue loan 15 days late, run the job | Status "Defaulted"; trust drops by 150 more | ☐ | ☐ |

Time-travel commands (laptop, in `backend/`; replace `ID` with the loan id from the app URL or the Lending list):

```sh
# LOAN-17: make it overdue
php artisan tinker --execute 'App\Models\Loan::find(ID)->forceFill(["due_date" => now()->subDay()->toDateString()])->save();'
php artisan loans:process-due

# LOAN-18: due in 2 days
php artisan tinker --execute 'App\Models\Loan::find(ID)->forceFill(["due_date" => now()->addDays(2)->toDateString()])->save();'
php artisan loans:process-due && php artisan loans:process-due

# LOAN-19: 15 days late
php artisan tinker --execute 'App\Models\Loan::find(ID)->forceFill(["due_date" => now()->subDays(15)->toDateString()])->save();'
php artisan loans:process-due
```

### 3.9 Trust score and anti-collusion (TRUST)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| TRUST-01 🔥 | Profile → trust card | Score, tier, and exactly three lines: Repayment, Marketplace, Vouches | ☐ | ☐ |
| TRUST-02 | New account (AUTH-09), verified | 500 · Basic, message "New accounts stay at Basic for their first 14 days." | ☐ | ☐ |
| TRUST-03 | New account, not verified | Tier Restricted, message "Verify your university email to start borrowing." | ☐ | ☐ |
| TRUST-04 | As B, open A's profile → "Vouch for this student" | A's Vouches line goes up by 25 (or 12 if A also vouched for B) | ☐ | ☐ |
| TRUST-05 | As B → "Withdraw my vouch" | A's Vouches line goes back | ☐ | ☐ |
| TRUST-06 | Open your own profile from a link | No vouch button for yourself | ☐ | ☐ |
| TRUST-07 | Circular lending: B lends to A (full loan, LOAN-05 to 13), then A lends to B (same steps the other way) | Both trust cards show "Loans went back and forth within a small circle…"; neither can reach Trusted | ☐ | ☐ |
| TRUST-08 | Fair loan (counts): do a full loan, then before the last repayment confirmation run the command below, then confirm | Repayment points **go up** this time (more than 48 hours between handover and repayment) | ☐ | ☐ |
| TRUST-09 | AI on (0.4): open the trust card | No change for a normal student; if the AI lowers a limit, the message "Our risk check expects a higher chance…" appears and the Ask-for-a-loan screen shows the lower limit | ☐ | ☐ |
| TRUST-10 | AI service stopped | Trust card and loans still work (rules only) | ☐ | ☐ |

```sh
# TRUST-08: pretend the cash was handed over 3 days ago
php artisan tinker --execute 'App\Models\Loan::find(ID)->forceFill(["activated_at" => now()->subDays(3)])->save();'
```

### 3.10 Messaging (MSG)

Use two devices: A on the phone, B on the second phone or the browser.

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| MSG-01 🔥 | As A, Home → chat icon | Inbox: 3 demo chats; the first is bold with an unread count | ☐ | ☐ |
| MSG-02 | Open the unread chat, go back | Unread count gone; chat icon badge goes down | ☐ | ☐ |
| MSG-03 🔥 | Open B's profile → "Message Test User Two" → send "hello" | Message appears on the right, purple, with the time | ☐ | ☐ |
| MSG-04 🔥 | On B's device, open Messages | Chat with Test User at the top, unread; message visible | ☐ | ☐ |
| MSG-05 🔥 | Keep both chats open; B replies | A sees the reply within about 4 seconds without touching anything | ☐ | ☐ |
| MSG-06 | After B opens the chat | A's message shows "· Seen" | ☐ | ☐ |
| MSG-07 | Message from a listing ("Message the seller") and from a loan ("Message the lender/borrower") | Opens the same chat with that student, never a duplicate | ☐ | ☐ |
| MSG-08 | Send button with an empty or spaces-only message | Button greyed out, nothing sent | ☐ | ☐ |
| MSG-09 | Paste a very long text (2,000+ characters) | Input stops at 2,000 characters; message sends and wraps correctly | ☐ | ☐ |
| MSG-10 | Send emoji 😀🎉 and Arabic text "مرحبا كيف حالك" | Both display correctly on both devices; Arabic reads right to left | ☐ | ☐ |
| MSG-11 | Type with the keyboard open | Input stays above the keyboard; last message visible | ☐ | ☐ |
| MSG-12 | Chat "…" → Report → pick a reason | Green "Report sent…" message | ☐ | ☐ |
| MSG-13 | Chat "…" → Block → Block | Green "You blocked…" message; sending now shows "You cannot message this student." | ☐ | ☐ |
| MSG-14 | On B's device, try to send to A after A blocked B | Refused with the same message | ☐ | ☐ |
| MSG-15 | Inbox after blocking | That chat shows "(blocked)" | ☐ | ☐ |
| MSG-16 | Unblock (until there is an unblock button, use the command below) | Messages work again | ☐ | ☐ |
| MSG-17 | Restriction: A, B and C each report the same demo student X; then sign in as X and try to message someone new | X is refused ("cannot start new conversations…"); X can still reply in an existing chat | ☐ | ☐ |
| MSG-18 | Check the database holds no readable messages | `SELECT body FROM messages LIMIT 3;` shows long `eyJpdi…` strings, not your text | ☐ | ☐ |
| MSG-19 | Sign in as U (unverified), open any chat "…" → Report | Refused ("Your email address is not verified."); only verified students' reports count | ☐ | ☐ |

```sh
# MSG-16: unblock (A unblocks everyone)
php artisan tinker --execute 'App\Models\Block::where("blocker_id", App\Models\User::firstWhere("email","202300000@ua.edu.lb")->id)->delete();'
# MSG-17: get a demo student X to report, and sign in as them (password: password)
php artisan tinker --execute 'echo App\Models\User::where("email","not like","2023000%")->first()->email;'
# MSG-17: see open reports, then lift X's restriction (marks their reports reviewed)
php artisan reports:review
php artisan reports:review X_EMAIL
```

### 3.11 Notifications (NOTIF)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| NOTIF-01 🔥 | As A, tap the bell | List of notifications; unread ones have a purple border | ☐ | ☐ |
| NOTIF-02 | B sends A 3 messages in a row without A reading | A gets **one** message notification, not three | ☐ | ☐ |
| NOTIF-03 | Tap a message notification | Opens that chat | ☐ | ☐ |
| NOTIF-04 | Tap a loan notification | Opens that loan | ☐ | ☐ |
| NOTIF-05 | Open the bell, go back to Home | Bell badge cleared | ☐ | ☐ |
| NOTIF-06 | Empty state (fresh account) | "Nothing new" | ☐ | ☐ |
| NOTIF-07 | Tap a badge notification | Opens My activity | ☐ | ☐ |

### 3.11b My activity and badges (DASH)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| DASH-01 🔥 | As A, Profile → My activity and badges | Badges "1 of 9 earned": First sale in color, the others grey with progress (Study buddy 2/3, Event goer 2/3) | ☐ | ☐ |
| DASH-02 | Scroll down | "This semester" bar chart with a legend; Marketplace $10 sold with an Earned/Spent chart; Lending $15 borrowed, "paid back 1 of 1 loan on time"; Study groups; Events and projects | ☐ | ☐ |
| DASH-03 🔥 | As A, join a study group with room, then open the bell | "You earned a badge: Study buddy"; tapping it opens My activity with "2 of 9 earned" | ☐ | ☐ |
| DASH-04 | As B, open A's profile | A "Badges" card with only A's earned badges | ☐ | ☐ |
| DASH-05 | Sell an item as B to C (first sale for B) | B gets "You earned a badge: First sale" right away | ☐ | ☐ |
| DASH-06 | Event goer: as C, be going to an event 1 hour from now that is C's third; wait until it starts and run `php artisan badges:award` | C gets "Event goer" (this one is checked hourly, after the event starts) | ☐ | ☐ |
| DASH-07 | Badges never touch the score: note A's trust score, earn a badge, look again | Same score | ☐ | ☐ |
| DASH-08 | Smallest phone (iPhone SE size) | Badge tiles wrap, no cut text, bar labels readable | ☐ | ☐ |

### 3.12 Robustness (ROB)

| ID | Steps | Expected | iOS | Android |
|---|---|---|---|---|
| ROB-01 🔥 | Airplane mode, then open any tab or send a message | "Cannot reach the server. Check your connection and the API address." No crash, no blank screen | ☐ | ☐ |
| ROB-02 | Turn the network back on, pull to refresh | Data loads again | ☐ | ☐ |
| ROB-03 | Stop `php artisan serve`, use the app | Same friendly "Cannot reach the server" message | ☐ | ☐ |
| ROB-04 | Break the database (stop MySQL), sign in | "Something went wrong on our side…", **never** an SQL error on screen | ☐ | ☐ |
| ROB-05 | App in the background for 5 minutes, then back | Returns to the same screen; data refreshes; open chat resumes polling | ☐ | ☐ |
| ROB-06 | Android only: hardware back button on every screen | Goes back one screen; on Home it leaves the app | n/a | ☐ |
| ROB-07 | Phone text size set to the largest (Settings → Display) | Text grows, nothing overlaps or gets cut in a way that hides buttons | ☐ | ☐ |
| ROB-08 | Phone in dark mode | All text still readable (the app stays light) | ☐ | ☐ |
| ROB-09 | Smallest phone you can borrow (e.g. iPhone SE) | Buttons reachable; nothing hidden behind the keyboard or the notch | ☐ | ☐ |
| ROB-10 | Tap a button many times quickly (Post listing, Fund loan, Send) | Only one item/action is created | ☐ | ☐ |
| ROB-11 | Signed in, airplane mode, close the app fully and reopen it | "You are still signed in" with Try again and Sign out, never the sign-in screen; Try again after turning the network on opens Home | ☐ | ☐ |

---

## 4. Before the demo or deployment (DEPLOY)

| ID | Check | Expected | ☐ |
|---|---|---|---|
| DEPLOY-01 | `backend/.env` on the server: `APP_ENV=production`, `APP_DEBUG=false` | Errors never show code or SQL | ☐ |
| DEPLOY-02 | HTTPS on the API URL (padlock in the browser) | Required for the "encrypted in transit" claim to the supervisor | ☐ |
| DEPLOY-03 | `CACHE_STORE` is `database` or `redis`, **never** `array` | With `array`, signed-out tokens keep working | ☐ |
| DEPLOY-04 | `APP_KEY` and `JWT_SECRET` are set and backed up | Changing `APP_KEY` makes every stored message unreadable | ☐ |
| DEPLOY-05 | Scheduler: cron `* * * * * php /path/artisan schedule:run` | Overdue loans, reminders and the nightly trust recalculation happen by themselves | ☐ |
| DEPLOY-06 | Mail configured (not `log`) | Verification emails arrive | ☐ |
| DEPLOY-07 | `php artisan app:e2e --base=https://…/api/v1` | All checks pass | ☐ |
| DEPLOY-08 | ML service running with `ML_SERVICE_TOKEN` set and the same value as `TRUST_MODEL_TOKEN` in Laravel | AI on, protected | ☐ |
| DEPLOY-09 | ML results reproduce: `python -m unicircle_ml.train` and `python -m unicircle_ml.rec_train` | Numbers match `ml/reports/RESULTS.md` and `RECOMMENDER.md` | ☐ |

---

## 5. Reporting a bug

Copy this into a GitHub issue (one bug per issue):

```
Test ID: MSG-05
Device: iPhone 13, iOS 18 / Pixel 6, Android 15 / browser
Account: A (202300000)
Steps: 1. ... 2. ... 3. ...
Expected: (from the plan)
Actual: what happened
Screenshot / screen recording: attach
Severity: Blocker (cannot continue) / Major (feature wrong) / Minor (looks wrong) / Cosmetic
```

Fix blockers and majors before the usability study. Retest the failed ID and its 🔥 neighbours after every fix.

---

## 6. Sign-off

| Layer | Result | Date | By |
|---|---|---|---|
| 1. Automated (CI green on the last PR) | | | |
| 2. `php artisan app:e2e` (135/135) | | | |
| 3. Manual, iPhone (all IDs ticked) | | | |
| 3. Manual, Android (all IDs ticked) | | | |
| 4. Deployment checklist | | | |

## 7. Bugs found while preparing this plan (already fixed)

| Bug | Severity | Fix |
|---|---|---|
| An API call without a token returned a 500 error instead of 401 when the client did not ask for JSON | Major | The API never redirects to a web login page now; 2 regression tests |
| Students were signed out every hour: the 60-minute token was never renewed, and the refresh endpoint rejected expired tokens | Major | Refresh accepts recently expired tokens; the app renews the token once and retries; 3 new tests; proven with 1-minute tokens in the browser |
| The sign-in screen showed raw database errors (SQL) when the server failed | Major | The app shows a friendly message for any server error; production must also run with `APP_DEBUG=false` (DEPLOY-01) |
| The chat "…" menu (report, block) did nothing in the browser build | Minor | Replaced the system alert with an in-app sheet that works on every platform |
| The app could not run in a browser at all (needed for the second test account) | Minor | Added Expo web support and a browser fallback for storing the login token |
| Demo data priced notes at up to $33 | Cosmetic | Realistic price ranges per category |

## 8. Bugs found in the full test pass of 2026-11-24 (fixed)

| Bug | Severity | Fix |
|---|---|---|
| Three unverified throwaway accounts could report a student and stop them starting chats, permanently | Major | Only verified students can report, only their reports count, sign-ups limited to 10 a minute per address, `php artisan reports:review` lists and clears reports; 6 tests |
| Home said "Picked for you…" while the AI service was down | Minor | The feed reports `rules` whenever a ranking fell back; 2 tests |
| "Seen" only appeared after leaving and reopening a chat | Minor | Each poll returns `seen_up_to`, the chat marks older messages seen; 1 test |
| Opening the app while the server was unreachable showed the sign-in screen | Minor | "You are still signed in" screen with Try again and Sign out (ROB-11) |
| Any direct link opened Home on a fresh start | Minor | Screens mount only after the stored login is restored |
| A report could attach a chat the reporter is not part of | Minor | The chat must be between the two students; 1 test |
| `php artisan test` failed on a laptop whose `.env` turns the AI on (step 0.4) | Minor | `phpunit.xml` switches the AI off for tests |
| AI-lowered limit was refused with "Your trust score is too low" | Cosmetic | Says the risk check set the limit; 1 test |
| Searching `%` or `_` matched every listing | Cosmetic | Search treats them as plain characters (listings, courses, students); 1 test |
| Demo data differed on every reset | Cosmetic | `DemoSeeder` is seeded: every reset gives the same students, scores and chats (A = 543, Basic, 3 chats) |

## 9. Bugs found in the full test pass of 2026-11-25 (fixed)

| Bug | Severity | Fix |
|---|---|---|
| Moving an event's start past its end time was accepted (the event ended before it started) | Minor | When only the start moves, the end moves with it, so the event keeps its length; 2 tests |
| A rescheduled event got no reminder for its new time | Minor | Reminders are counted per event and start time; 1 test (EVT-14) |
| Opening something deleted showed "No query results for model [App\Models\Event] 12" | Minor | Any missing record says "This was removed or no longer exists."; 1 test (EVT-15) |
| Deleting a project told nobody | Minor | Members and waiting students get "… was deleted"; 1 test (PRJ-15) |
| When a team filled up, the other requests stayed pending forever | Minor | They are removed and told "The team for … is full"; they can ask again if a spot opens; 1 test (PRJ-14) |
| Owners could not edit an event or project in the app (delete and repost was the only way, which told attendees it was cancelled) | Gap | "Edit event" and "Edit project" screens (EVT-12, EVT-13, PRJ-12, PRJ-13) |
| Demo times showed 2 hours late on a Lebanese phone | Cosmetic | `DemoSeeder` sets clock times in Beirut time |
| An unknown topic filter returned everything | Cosmetic | Refused with 422 on events and projects; 2 tests |
| "Marketplace" tab label cut to "Marketpl…" on a 320 pt phone | Cosmetic | Smaller tab labels below 360 pt wide |
