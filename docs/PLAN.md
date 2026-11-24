# UniCircle: Build Plan and Status

Single source of truth for what is left to build. Update the status column as work lands.

**Last updated:** 2026-11-24 · **Current week:** 12 of 16

> Timeline assumption: Week 1 = Mon 7 Sep 2026, Week 16 ends Sun 27 Dec 2026. Shift every row if the defense date says otherwise.

Status legend: ✅ done · 🟡 in progress · ⬜ not started · 🔵 stretch

---

## 1. Where we are

| Area | Status | Notes |
|---|---|---|
| Auth: JWT, university email (`@ua.edu.lb`, both campuses confirmed), verification, refresh | ✅ | |
| Profile + courses | ✅ | |
| Study groups | ✅ | Ranked by the recommender (PR #4), SQL fallback |
| Marketplace (listings, search, sold flow, ratings) | ✅ | |
| P2P lending ledger (IOU only, state machine, tier caps) | ✅ | |
| Rule-based trust score (events, tiers, nightly recalc, history) | ✅ | Anti-collusion added (PR #2) |
| Vouches + public student profile | ✅ | |
| Mobile app, 5 tabs (Expo Router) | ✅ | The second tab is now **Campus**: study groups, events and projects |
| Backend tests | ✅ | 341 passing on MySQL 8 and SQLite + 38 ML tests, CI on every PR |
| Test plan + end-to-end script | ✅ | PR #6: `docs/TEST-PLAN.md` (iPhone + Android checklist), `php artisan app:e2e` (127 checks), browser build for the second account; 3 real bugs fixed |
| Messaging + notifications | ✅ | PR #5: chats, block/report, notifications list, header badges |
| Home feed + recommender | ✅ | PR #4: groups and listings; PR #8: events and projects too |
| Demo data | ✅ | `DemoSeeder` (40 students, groups, listings, loans, events, projects, interests and skills), identical on every reset |
| AI trust model (Flask, gradient boosting) | ✅ | Can only lower a tier; rules decide if the service is down |
| Interests and skills on profiles | ✅ | PR #8: 8 topics, up to 10 skills; shown on public profiles |
| Event & project hub | ✅ | PR #8: events (topics, capacity, RSVP, day-before reminder, cancel notice), projects (skills needed, join requests, accept or decline, close), both recommended |
| Usability study kit | ✅ | PR #8: `docs/usability/` (task script, Google Form, scoring script, results template) |

## 2. Phases

| Week | Phase | Work | Done when | Status |
|---|---|---|---|---|
| 9 | 0. Hygiene | Root CI running the suite on MySQL 8 and SQLite; fix MySQL-only failures; remove unused `backend/.github` | CI green on MySQL | ✅ PR #1 (no MySQL failures found) |
| 9 | 1. Anti-collusion | Same-pair cap, minimum loan duration, counterparty diversity, mutual-vouch discount, voucher tier gate, circular-activity flag; engagement and account-age points removed from the score | Tests show the fake-loan attack stays in the basic tier | ✅ PR #2 (attack: 885 → 592) |
| 9 | Docs | Written response to the supervisor memo (12 points), trust formula, Must/Should table, revised Gantt | `docs/supervisor-response.md` | ✅ draft in PR #2 |
| 10 | 2. AI trust service | Flask service, synthetic data with injected bad actors, rule baseline vs logistic regression / gradient boosting, AUC, Brier, reliability diagram, ring recall; Laravel calls `/trust/assess` with rule fallback | Model beats the baseline on held-out and shifted data | ✅ PR #3 (AUC 0.775 vs 0.646; done early, Week 9) |
| 10 | Docs | Verify or replace every proposal reference (DOI or URL) | Every reference checked | ✅ 2 of 7 real; 5 replaced (`docs/references-check.md`); proposal .docx still to update |
| 11 | 3. Recommender | Flask: groups + listings (course, classmates, past partners, time of day, TF-IDF, price); Laravel cache; mobile home feed | precision@5 / recall@5 vs SQL baseline | ✅ PR #4 (done early, Week 10) |
| 12 | 4. Messaging + notifications | 1:1 chat from profile, listing, loan; polling; bodies encrypted at rest + HTTPS; block/report; in-app notifications (database channel) for loans and messages | Two phones chat, DB stores ciphertext, due-loan reminder appears | ✅ PR #5 (done early, Week 10); phone test pending |
| 13 | 5. Event & project hub + evaluation | Events (create, RSVP, tags) + projects (skills, join requests) + recommendations for both; SUS kit (tasks, form, scoring script) | Mario runs SUS with 5 to 8 students | 🟡 built in PR #8 (early, Week 12); SUS sessions to run |
| 14 | 6. Analytics + badges, freeze | Personal dashboard, badge rules; security pass; load test on feed, listings, recommendations | Feature freeze | ⬜ |
| 15–16 | Report + defense | Chapters, slides, two rehearsals | | ⬜ |
| Stretch | | Push notifications to the phone (needs an Expo dev build) | | 🔵 |

## 3. Proposal coverage

Nothing from the original proposal is dropped. Only the order changed, as the supervisor asked.

| Proposal item | Phase |
|---|---|
| Verified identity | ✅ |
| Smart collaboration (study groups) | ✅ + Phase 3 |
| Safe marketplace with AI recommendations | ✅ + Phase 3 |
| P2P lending with AI trust score | ✅ + Phases 1, 2 |
| Recommendations: groups, items, events, projects | ✅ Phases 3, 5 |
| Encrypted messages | Phase 4 |
| Automatic notifications | Phase 4 |
| Event & project hub | ✅ Phase 5 |
| Engagement analytics & badges | Phase 6 |
| Testing (now concrete) | Phases 0, 2, 3, 5 |

In the supervisor response, events, analytics and badges are labeled stretch (his request). They are still built.

## 4. Decisions log

| Date | Decision |
|---|---|
| 2026-11-06 | AI service and evaluation move ahead of secondary features (supervisor memo) |
| 2026-11-06 | Messaging stays in core, encrypted at rest + HTTPS in transit, no end-to-end claim |
| 2026-11-06 | Trust score drops engagement and account-age points, back to the proposal's three signals: transactions, vouches, repayment |
| 2026-11-06 | `@ua.edu.lb` confirmed for ZA and BA students |
| 2026-11-06 | Mario builds all remaining work |
| 2026-11-06 | Trust weights: repayment 60%, marketplace 25%, vouches 15%; new verified student = 500 = basic |
| 2026-11-06 | AI model gates the tier (block a size when risk ≥ 30%), never raises it; displayed score stays rule-based |
| 2026-11-06 | Laravel sends raw history, Python computes features (one implementation) |
| 2026-11-09 | Recommender uses the same pattern; SQL ordering stays as fallback and as the evaluation baseline |
| 2026-11-09 | Testing in three layers: CI, `app:e2e` against the running server, manual checklist on both phones; never run with `CACHE_STORE=array` |
| 2026-11-09 | Any verified student can message any other; block stops both ways; 3 unreviewed reports from different students stop new chats |
| 2026-11-24 | Projects are back in Phase 5 (the proposal promises an "Event & Project hub"); interests and skills added to profiles to match "interests and skills" |
| 2026-11-24 | Events and projects live in a Campus tab (study groups / events / projects) instead of a sixth tab |
| 2026-11-24 | Going to events and joining projects never changes the trust score (the score keeps its three signals) |
| 2026-11-24 | Event and project recommendations follow the same pattern: model in Flask, SQL ordering as fallback and baseline; they run on their own random stream so the group and listing results do not change |
| 2026-11-24 | SUS questionnaire in Google Forms; facilitator plays the second student in the browser |

## 5. What Mario owns

- Run `docs/TEST-PLAN.md` on iPhone and Android (about 3 hours each) and log bugs as GitHub issues
- Run the SUS study with 5 to 8 students (Week 13)
- Merge the PRs
- Get the defense date
