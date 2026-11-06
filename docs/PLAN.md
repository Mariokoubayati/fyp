# UniCircle: Build Plan and Status

Single source of truth for what is left to build. Update the status column as work lands.

**Last updated:** 2026-11-06 · **Current week:** 9 of 16

> Timeline assumption: Week 1 = Mon 7 Sep 2026, Week 16 ends Sun 27 Dec 2026. Shift every row if the defense date says otherwise.

Status legend: ✅ done · 🟡 in progress · ⬜ not started · 🔵 stretch

---

## 1. Where we are

| Area | Status | Notes |
|---|---|---|
| Auth: JWT, university email (`@ua.edu.lb`, both campuses confirmed), verification, refresh | ✅ | |
| Profile + courses | ✅ | |
| Study groups (SQL matching by classmates) | ✅ | Ranking upgraded by the recommender in Week 11 |
| Marketplace (listings, search, sold flow, ratings) | ✅ | |
| P2P lending ledger (IOU only, state machine, tier caps) | ✅ | |
| Rule-based trust score (events, tiers, nightly recalc, history) | ✅ | Anti-collusion added in Phase 1 |
| Vouches + public student profile | ✅ | |
| Mobile app, 5 tabs (Expo Router) | ✅ | |
| Backend tests | ✅ | 222 passing (SQLite) on 2026-11-06 |

## 2. Phases

| Week | Phase | Work | Done when | Status |
|---|---|---|---|---|
| 9 | 0. Hygiene | Root CI running the suite on MySQL 8 and SQLite; fix MySQL-only failures; remove unused `backend/.github` | CI green on MySQL | 🟡 |
| 9 | 1. Anti-collusion | Same-pair cap, minimum loan duration, counterparty diversity, mutual-vouch discount, voucher tier gate, circular-activity flag; engagement and account-age points removed from the score | Tests show the fake-loan attack stays in the basic tier | ⬜ |
| 9 | Docs | Written response to the supervisor memo (12 points), trust formula, Must/Should table, revised Gantt | `docs/supervisor-response.md` | ⬜ |
| 10 | 2. AI trust service | Flask service, synthetic data with injected bad actors, rule baseline vs logistic regression / gradient boosting, AUC, Brier, reliability diagram, ring recall; Laravel calls `/trust/score` with rule fallback | Model beats the baseline on held-out and shifted data | ⬜ |
| 10 | Docs | Verify or replace every proposal reference (DOI or URL) | Every reference checked | ⬜ |
| 11 | 3. Recommender | Flask: groups + listings (course and schedule overlap, TF-IDF, co-occurrence); Laravel cache; mobile home feed | precision@5 / recall@5 vs SQL baseline | ⬜ |
| 12 | 4. Messaging + notifications | 1:1 chat from profile, listing, loan; polling; bodies encrypted at rest + HTTPS; block/report; in-app notifications (database channel) for loans and messages | Two phones chat, DB stores ciphertext, due-loan reminder appears | ⬜ |
| 13 | 5. Events hub + evaluation | Events (create, RSVP, tags) + event recommendations; SUS kit (tasks, form, scoring script) | Mario runs SUS with 5 to 8 students | ⬜ |
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
| Recommendations: groups, items, events | Phases 3, 5 |
| Encrypted messages | Phase 4 |
| Automatic notifications | Phase 4 |
| Event & project hub | Phase 5 |
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

## 5. What Mario owns

- Test every new screen on a real phone
- Run the SUS study with 5 to 8 students (Week 13)
- Merge the PRs
- Get the defense date
