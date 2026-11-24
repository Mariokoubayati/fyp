# Session script and tasks

## Intro (read out loud)

> Thanks for helping. We are testing an app called UniCircle, not you, so there are no wrong answers. If something is confusing, that is exactly what we want to find out.
>
> I will give you 7 small tasks. Please say out loud what you are thinking while you do them, for example "I'm looking for…" or "I expected this button to…". I won't help unless you are really stuck. Tell me when you think a task is done.
>
> It takes about 15 minutes, then a short questionnaire. You can stop at any time. Ready?

The phone is signed in as Test User and open on Home.

## Tasks

Read each task exactly as written. Start the timer when you finish reading. Stop it when they say "done", give up, or after 4 minutes.

| # | Read this | Done when | Feature |
|---|---|---|---|
| T1 | "You want to study with others for one of your courses. Find a study group that has room and join it." | Group detail shows "Leave group" (they are a member) | Study groups |
| T2 | "Find a sports event happening on campus and tell the organizers you're going." | A sports event shows "I can't go anymore" | Events |
| T3 | "You also like art, and you know how to use Figma. Add both to your profile." | Profile shows the Arts and Figma pills | Interests and skills |
| T4 | "Find a student project that needs Laravel and ask to join it with a short message." | Project shows "Request sent" | Projects |
| T5 | "You want to sell your scientific calculator for 12 dollars. Put it on sale." | The listing page opens | Marketplace |
| T6 | "You need 10 dollars for lunch and will pay it back next week. Ask for a loan." | Loan shows "Looking for a lender" | Lending |
| T7 | "Send a message to the student who posted the Board games night event." | Chat opens with a sent message | Messaging |

**You (as Test User Two, in the browser):** after T4, accept the request so they see the notification later if they open the bell; after T7, reply "Hi!" so they see the chat update.

## What to write down (one row per task in `observations.csv`)

| Column | Write |
|---|---|
| `participant` | P1, P2… |
| `task` | T1 … T7 |
| `result` | `success` (alone), `help` (you gave a hint), `fail` (gave up or 4 minutes) |
| `seconds` | Time on the task |
| `errors` | Wrong turns: wrong tab, wrong button, went back (a count) |
| `notes` | What they said or where they hesitated, in their words if you can |

## Closing questions (ask before the form, write the answers in notes)

1. "What was the easiest thing to do?"
2. "What was the most confusing moment?"
3. "Is there anything you expected to find and couldn't?"

Then give them the Google Form.
