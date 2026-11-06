# UniCircle: Response to Supervisor Feedback (Oct 23, 2026)

**To:** Dr. Ahmad Kassem
**From:** Ahmad Shmoury (202312277), Mario Koubayati (202310441)
**Date:** November 2026 (Week 9 of 16)

Thank you for the feedback. Below is our answer to each point, followed by the items you asked for. Points marked **Done** are already in the repository with tests.

---

## Critical issues

### 1. Collusion in the trust score: Done

We agree, this was a real weakness. Two friends could reach the $100 tier in about a week with fake $5 loans. We added six rules. All values live in `config/trust.php`.

| Rule | What it stops |
|---|---|
| Only 2 rewards from the same partner per component in any 90 days | One friend repeating fake loans, sales or 5-star ratings |
| A loan repaid less than 48 hours after handover is recorded but earns nothing | Instant "lend and repay" loops |
| Repayment points are multiplied by min(1, different lenders / 3) | Building a score with only one or two partners |
| Vouches from unverified students or students under 400 count 0, and mutual vouches (A for B and B for A) count half | Vouch swapping and fake accounts vouching |
| Circular lending (A to B to A, or A to B to C to A) within 90 days keeps the tier at Basic and shows a flag | Closed rings passing the same money around |
| Accounts younger than 14 days cannot reach the top tier | Fresh accounts created only to collude |

Penalties (overdue, default) are never reduced by these rules.

**Evidence:** an automated test runs the attack through the real API. Alice borrows from Bob 6 times, sells him 3 items rated 5 stars, and Bob vouches for her.

| | Score | Tier |
|---|---|---|
| Before the rules | 885 | Trusted ($100) |
| After the rules | 592 | Basic ($20) |
| Honest student repaying 5 different lenders | 700 | Trusted ($100) |

Two friends lending back and forth, and a ring of three, are both flagged and stay at Basic. The trade-off: two honest friends who genuinely lend to each other are also held at Basic ($20). We accept this. They can still borrow, and the flag clears once the loans leave the 90-day window.

### 2. AI calibration plan: Done (first results below)

**a. What "calibration" means.** A score is calibrated when the default rate we observe matches the risk the score predicts. If 100 students are predicted to have a 5% chance of default, about 5 of them should default. We measure this with a reliability diagram (predicted vs observed default rate per score band) and the Brier score.

**b. How the synthetic data is generated.** To avoid the circularity you pointed out, the generator does not use our trust rules. Each simulated student gets a hidden reliability value and one behavior type:

| Type | Share | Behavior |
|---|---|---|
| Reliable | ~55% | Repays on time with high probability |
| Flaky | ~20% | Often late, sometimes defaults |
| Newcomer | ~10% | Little history |
| Strategic defaulter | ~8% | Repays small loans to build a score, then defaults on a large one |
| Collusion ring | ~7% | Groups of 2 to 4 that trade fake loans, then one member borrows from an outsider and defaults |

Whether a loan defaults depends only on the hidden reliability, the loan size, the type and an exam-season shock that no feature can see. The trust rules never see the hidden values, and the generator never uses the rules. Bad actors are injected at known rates so we can measure how many each method catches. They also behave realistically: rings cash out at the $15 to $25 Basic limit, and strategic defaulters grow their loans slowly so the last one does not stand out.

**c. How we show the AI beats the rule-based baseline.** Task: predict whether a student's next loan defaults, using only their history before that loan.

- **Baselines:** the current rule-based score, and a stronger one that adds the loan size, so the model gets no credit just for seeing the amount.
- **Models:** logistic regression and gradient boosting on behavior features (repayment rate, number of different lenders, how much activity is with the same partners, loan size compared with past loans, vouch reciprocity).
- **Metrics:** ROC-AUC, Brier score, reliability diagram, money lost at the same approval rate, and the share of ring members caught.
- **Against circularity:** we also test on (1) a generator with shifted parameters, and (2) a bad-actor type left out of training. If the model only learned our generator, it will fail these tests, and we will report that honestly.

**First results** (gradient boosting, the full tables and plots are in `ml/reports/RESULTS.md`):

| Test | Rules AUC | Rules + amount AUC | AI AUC | Ring cash-outs caught (AI vs rules) |
|---|---|---|---|---|
| New world, same settings | 0.646 | 0.663 | **0.775** | 99% vs 0% |
| Shifted world | 0.599 | 0.686 | **0.787** | 99% vs 0% |
| Unseen type (no strategic defaulters in training) | 0.646 | 0.665 | **0.755** | 100% vs 0% |

As a lending decision, adding the AI check to the rules cuts the money lent to students who then default by 16% in the normal world and 27% in the shifted world, while still allowing 99% of the good requests the rules allowed. The model is well calibrated (expected calibration error under 0.02 in every test).

**Limitation we found:** trained without strategic defaulters, the model only ranks 10% of their cash-outs among the riskiest 20% (64% when it has seen them). It still beats the rules on AUC, but it does not discover a new fraud pattern by itself. Retraining as new patterns appear is part of the design.

**How it is used in the app:** the model runs in a Python (Flask) service. For each loan size the rules allow ($20, $100), it predicts the default risk. The tier drops to the largest size with a risk under 30%. The model can only lower a tier, never raise it. The score students see stays the explainable rule-based score, with a message when the AI check lowered their limit. If the service is down, the rules decide alone.

### 3. Evaluation plan

| What | How | Result we report |
|---|---|---|
| Loan state machine | Automated feature tests on every transition and invalid transition | Test count and pass rate (CI on MySQL 8 and SQLite) |
| Anti-collusion | Attack scenarios run through the API (point 1) | Score and tier before and after |
| AI trust score | Held-out and shifted synthetic data (point 2) | AUC, Brier, reliability diagram, ring recall vs baseline |
| Recommender | Hold out part of each student's interactions, recommend top 5 | precision@5 and recall@5 vs the current SQL ranking |
| Usability | System Usability Scale with 5 to 8 students doing set tasks (find a group, list an item, request a loan, send a message) | SUS score (0 to 100) and observed issues |
| Load | Simple load test on feed, listings and recommendations | Response times under load |

Today the backend has 243 automated tests, all passing on MySQL 8 and SQLite, and the ML service has 21.

---

## Major issues

### 4. Scope

Agreed. The AI service and evaluation now come right after the anti-collusion work (Weeks 10 and 11). Events, analytics and badges are now stretch goals and come after the core features. Messaging and notifications stay in core because they are part of the proposal's objectives.

### 5. Timeline

We are in **Week 9 of 16** (Week 1 started Monday, September 7). See the Gantt chart below.

### 6. Trust formula

Score = 500 + repayment + marketplace + vouches, limited to 0 to 1000. Each component is capped.

| Component | Weight | Cap (points) | Points |
|---|---|---|---|
| Loan repayment | 60% | ±300 | On time +40, late +10, overdue -60, default -150 |
| Marketplace | 25% | ±125 | Sale +20, purchase +10, rating (stars - 3) x 10 |
| Vouches | 15% | ±75 | +25 each (rules in point 1) |

| Tier | Score | Max loan |
|---|---|---|
| Restricted | 0 to 399 | $0 |
| Basic | 400 to 699 | $20 |
| Trusted | 700 to 1000 | $100 |

**A new verified student starts at 500, which is the Basic tier ($20).** Unverified students cannot borrow.

**Engagement and account age:** we agree both raised fairness problems. Engagement rewarded free time (students who commute or work have less of it), and account age rewarded seniority rather than reliability. We removed both from the score. This also brings the formula back to the three signals in our proposal: transactions, friend verifications and repayment. Account age now only appears as the 14-day waiting period before the top tier, which is an anti-fraud rule and not a reward.

### 7. Database: Done

The full test suite now runs on MySQL 8 (strict mode) and SQLite in CI on every pull request, and fresh migrations and seeding run on MySQL. All tests pass on both. We found no MySQL-specific failures.

### 8. Messaging

"Encrypted" now means two things only: message bodies are **encrypted at rest** in the database (Laravel encrypted cast, AES-256), and all traffic is **encrypted in transit** over HTTPS. We do not claim end-to-end encryption. Messages are delivered by polling.

### 9. Security: Done

We checked the full history of the public repository. No `.env` file was ever committed, there are no keys or secrets, and the only email in the seed data is the placeholder `202300000@ua.edu.lb`.

### 10. Proposal and references

In progress (Week 10). Every reference will be checked. Any reference without a DOI or URL will be replaced with a verifiable source before the final phase.

---

## Minor issues

### 11. Email domain: Done

Confirmed: `@ua.edu.lb` covers students on both campuses (ZA and BA).

### 12. Must vs Should

| Priority | Items |
|---|---|
| **Must** | Verified identity, study groups with matching, marketplace, P2P lending, trust score with anti-collusion, AI trust model and its evaluation, recommender (groups and items) with precision@k, evaluation (tests, SUS) |
| **Should** | Messaging (encrypted at rest + HTTPS), in-app notifications |
| **Stretch** | Event and project hub with event recommendations, engagement analytics and badges, push notifications to the phone |

---

## Revised Gantt chart

Current week: **Week 9 (Nov 2 to Nov 8)** ◀

| Week | Dates | Work | Status |
|---|---|---|---|
| 1–2 | Sep 7 to Sep 20 | Requirements, mockups, trust score design | ✅ |
| 3–4 | Sep 21 to Oct 4 | Database design, Laravel and Expo setup, JWT auth | ✅ |
| 5–8 | Oct 5 to Nov 1 | Identity, study groups, marketplace, lending, rule-based trust score, vouches and ratings | ✅ |
| **9** ◀ | **Nov 2 to Nov 8** | **MySQL CI, anti-collusion rules, this response, AI trust service and its evaluation** | ✅ |
| 10 | Nov 9 to Nov 15 | Verified references, start of the recommender | ⬜ |
| 11 | Nov 16 to Nov 22 | Recommender (groups, items) and precision@k | ⬜ |
| 12 | Nov 23 to Nov 29 | Messaging and in-app notifications | ⬜ |
| 13 | Nov 30 to Dec 6 | Usability study (SUS); stretch: event hub | ⬜ |
| 14 | Dec 7 to Dec 13 | Security pass, load test, feature freeze; stretch: analytics and badges | ⬜ |
| 15 | Dec 14 to Dec 20 | Final report | ⬜ |
| 16 | Dec 21 to Dec 27 | Slides, rehearsals, defense | ⬜ |
