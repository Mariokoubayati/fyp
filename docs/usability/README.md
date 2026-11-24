# Usability study (SUS): how to run it

**Goal:** measure how easy UniCircle is to use with 5 to 8 real students, using the System Usability Scale (SUS) [Brooke 1996] plus task success and time. This is the evaluation chapter's evidence.

**Time per student:** about 25 minutes (5 intro, 15 tasks, 5 questionnaire).

## Files in this folder

| File | What it is |
|---|---|
| `tasks.md` | What you read out, the 7 tasks, and what to write down |
| `google-form.md` | How to build the questionnaire in Google Forms (consent, about you, the 10 SUS questions, 3 open questions) |
| `observations-template.csv` | One row per student per task: done or not, seconds, help needed |
| `score_sus.py` | Scores everything and prints the tables for the report |
| `results-template.md` | The structure of the results section, ready to fill |

## Before the first session (once)

1. Build the Google Form from `google-form.md` and do it once yourself as a test.
2. Laptop: backend running with the AI service on (test plan steps 0.1 to 0.4), phone on the same network.
3. Run the 🔥 smoke cases from the test plan on the phone you will use.
4. Print or open `tasks.md` and a copy of `observations-template.csv`.

## Before each student

Reset the data so everyone starts from the same campus:

```sh
cd backend
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
```

Then sign in on the phone as **Test User** (`202300000@ua.edu.lb`, password `password`) and leave it on Home. You play the "other student" (**Test User Two**) in the browser build when a task needs someone to answer (task 4 and task 7).

## During the session

- Read the intro in `tasks.md` word for word, so every student hears the same thing.
- Ask them to think aloud. Do not help unless they are stuck for a minute; if you help, write "help" for that task.
- Time each task from when you finish reading it to when they say they are done (or give up, or 4 minutes pass).
- Fill one row per task in the observations sheet.

## After the session

Send them the Google Form link on their own phone (or let them use yours). Put their participant ID (P1, P2…) in the first question so the form and your observations match.

## Scoring

1. Google Forms → Responses → the green Sheets icon → File → Download → CSV. Save it as `responses.csv`.
2. Save your observations as `observations.csv` (same columns as the template).
3. Run:

```sh
python docs/usability/score_sus.py responses.csv observations.csv > results.md
```

It prints each student's SUS score, the mean with a 95% confidence interval, the comparison with the average of 68, every item's mean, and task success and time. Paste it into `results-template.md`.

## Reading the result honestly

- An average SUS score across hundreds of studies is **68** (SD 12.5) [Sauro and Lewis]. Above 68 is above average; it is not a percentage.
- With 5 to 8 students the confidence interval is wide (often ±10 points). Report the interval, not only the mean.
- Bangor, Kortum and Miller (2009) map scores to adjectives ("OK", "Good", "Excellent"); if you use the adjectives, take the cut-offs from their paper and cite it.
- The open questions and the tasks where people needed help are usually more useful than the number itself: they say *what* to fix.

## References to add to the report

- J. Brooke, "SUS: A 'quick and dirty' usability scale," in *Usability Evaluation in Industry*, P. W. Jordan, B. Thomas, B. A. Weerdmeester, and I. L. McClelland, Eds. London, U.K.: Taylor & Francis, 1996, pp. 189–194. (already reference [11] in `references-check.md`)
- A. Bangor, P. Kortum, and J. Miller, "Determining what individual SUS scores mean: Adding an adjective rating scale," *Journal of Usability Studies*, vol. 4, no. 3, pp. 114–123, 2009.
- J. Sauro and J. R. Lewis, *Quantifying the User Experience: Practical Statistics for User Research*, 2nd ed. Cambridge, MA, USA: Morgan Kaufmann, 2016.
