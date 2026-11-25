# Building the questionnaire in Google Forms

Create a new form at forms.google.com. Title: **UniCircle usability questionnaire**. Description: *About 3 minutes. Anonymous: we do not collect your name or email.*

**Settings** (gear icon): Responses → turn **off** "Collect email addresses"; Presentation → turn **off** "Show link to submit another response".

Use **sections** (the "Add section" icon) so each part is its own page.

## Section 1: Consent

Add a **Checkboxes** question, **required**, with this as the question text and one option "I agree":

> I am taking part in a usability test of UniCircle, a student app built for a final year project at Antonine University. I used the app for about 15 minutes and now answer a short questionnaire. My answers are anonymous and are only used, as group results, in the project report. I can stop at any time without giving a reason.

## Section 2: About you

| Question | Type | Required | Options |
|---|---|---|---|
| Participant ID (the facilitator tells you, e.g. P3) | Short answer | Yes | |
| Year of study | Multiple choice | Yes | 1, 2, 3, 4, 5 or more |
| Major | Short answer | No | |
| Phone you used today | Multiple choice | Yes | iPhone, Android |
| How often do you use apps or WhatsApp groups for university things (study groups, selling books, events)? | Multiple choice | Yes | Every day, A few times a week, Rarely, Never |

## Section 3: The 10 SUS questions

Add **10 Linear scale** questions, all **required**, scale **1 to 5**, label for 1 **Strongly disagree**, label for 5 **Strongly agree**. Add this line to the section description: *"System" means the UniCircle app you just used.*

Type the titles **exactly** like this, starting with "SUS" and the number. The scoring script finds the columns by that start, and the order matters (odd questions are positive, even ones negative):

| Question title |
|---|
| SUS 1. I think that I would like to use this system frequently. |
| SUS 2. I found the system unnecessarily complex. |
| SUS 3. I thought the system was easy to use. |
| SUS 4. I think that I would need the support of a technical person to be able to use this system. |
| SUS 5. I found the various functions in this system were well integrated. |
| SUS 6. I thought there was too much inconsistency in this system. |
| SUS 7. I would imagine that most people would learn to use this system very quickly. |
| SUS 8. I found the system very cumbersome to use. |
| SUS 9. I felt very confident using the system. |
| SUS 10. I needed to learn a lot of things before I could get going with this system. |

Keep the original wording (it is the standard version from Brooke 1996). If a student asks what "cumbersome" means, you may say "awkward"; that substitution is common and does not change the scores.

## Section 4: In your own words

| Question | Type | Required |
|---|---|---|
| What did you like most? | Paragraph | No |
| What was the most frustrating or confusing part? | Paragraph | No |
| Is there anything you would add or change? | Paragraph | No |

## Before the first session

1. Click **Preview** (eye icon) and fill it once yourself.
2. Responses tab → click the green **Sheets** icon to create a linked sheet, then delete your test row from the sheet.
3. Send → link icon → shorten URL. Make a QR code of it so students can open it on their own phone.

## Exporting for scoring

Open the linked sheet → **File → Download → Comma Separated Values (.csv)** → save as `responses.csv`.
