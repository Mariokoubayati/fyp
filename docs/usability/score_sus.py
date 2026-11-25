"""Score the UniCircle usability study.

    python docs/usability/score_sus.py responses.csv [observations.csv]

responses.csv:     the Google Forms export (columns titled "SUS 1. ..." to "SUS 10. ...", and "Participant ID ...")
observations.csv:  participant,task,result,seconds,errors,notes  (result = success | help | fail)

Prints Markdown tables for the report. Standard library only.
"""

from __future__ import annotations

import csv
import math
import re
import sys
from collections import defaultdict
from statistics import mean, median, stdev

BENCHMARK = 68.0  # average SUS score across hundreds of studies (Sauro and Lewis)

# Two-sided 95% t critical values by degrees of freedom (n - 1), for small samples.
T95 = {1: 12.706, 2: 4.303, 3: 3.182, 4: 2.776, 5: 2.571, 6: 2.447, 7: 2.365, 8: 2.306, 9: 2.262, 10: 2.228,
       11: 2.201, 12: 2.179, 13: 2.160, 14: 2.145, 15: 2.131, 16: 2.120, 17: 2.110, 18: 2.101, 19: 2.093, 20: 2.086,
       25: 2.060, 30: 2.042}


def t_critical(df: int) -> float:
    if df in T95:
        return T95[df]
    smaller = [d for d in T95 if d <= df]
    return T95[max(smaller)] if smaller else 1.96


def sus_score(answers: list[int]) -> float:
    """Brooke (1996): odd items contribute answer - 1, even items 5 - answer; the sum times 2.5 gives 0 to 100."""
    if len(answers) != 10 or any(a < 1 or a > 5 for a in answers):
        raise ValueError(f"need 10 answers between 1 and 5, got {answers}")
    total = sum((a - 1) if i % 2 == 0 else (5 - a) for i, a in enumerate(answers))
    return total * 2.5


def sus_columns(header: list[str]) -> list[int]:
    """Index of the column for SUS 1 ... SUS 10, found by the title start."""
    found: dict[int, int] = {}
    for index, title in enumerate(header):
        match = re.match(r"\s*SUS\s*(\d{1,2})\b", title, re.IGNORECASE)
        if match and 1 <= int(match.group(1)) <= 10:
            found[int(match.group(1))] = index
    missing = [n for n in range(1, 11) if n not in found]
    if missing:
        raise SystemExit(f"Could not find the SUS questions {missing}. Titles must start with 'SUS 1.' ... 'SUS 10.'.")
    return [found[n] for n in range(1, 11)]


def participant_column(header: list[str]) -> int | None:
    for index, title in enumerate(header):
        if title.strip().lower().startswith("participant"):
            return index
    return None


def read_responses(path: str) -> list[tuple[str, list[int]]]:
    with open(path, newline="", encoding="utf-8-sig") as f:
        rows = list(csv.reader(f))
    header, data = rows[0], [r for r in rows[1:] if any(cell.strip() for cell in r)]
    columns, pid = sus_columns(header), participant_column(header)
    people = []
    for n, row in enumerate(data, start=1):
        name = row[pid].strip().upper() if pid is not None and row[pid].strip() else f"R{n}"
        people.append((name, [int(row[c]) for c in columns]))
    return people


def summary(scores: list[float]) -> dict[str, float]:
    n = len(scores)
    avg = mean(scores)
    sd = stdev(scores) if n > 1 else 0.0
    half = t_critical(n - 1) * sd / math.sqrt(n) if n > 1 else 0.0
    # SUS cannot go below 0 or above 100, so neither can the interval.
    return {"n": n, "mean": avg, "sd": sd, "low": max(0.0, avg - half), "high": min(100.0, avg + half),
            "min": min(scores), "max": max(scores)}


def report_sus(people: list[tuple[str, list[int]]]) -> None:
    scores = [sus_score(answers) for _, answers in people]
    s = summary(scores)

    print("## SUS results\n")
    print("| Participant | " + " | ".join(f"Q{i}" for i in range(1, 11)) + " | SUS score |")
    print("|---|" + "---|" * 10 + "---|")
    for (name, answers), score in zip(people, scores):
        print(f"| {name} | " + " | ".join(str(a) for a in answers) + f" | {score:.1f} |")

    print()
    print("| Measure | Value |")
    print("|---|---|")
    print(f"| Students | {s['n']} |")
    print(f"| Mean SUS score | **{s['mean']:.1f}** |")
    print(f"| Standard deviation | {s['sd']:.1f} |")
    print(f"| 95% confidence interval | {s['low']:.1f} to {s['high']:.1f} |")
    print(f"| Lowest / highest | {s['min']:.1f} / {s['max']:.1f} |")
    print(f"| Students above the average of {BENCHMARK:.0f} | {sum(1 for x in scores if x > BENCHMARK)} of {s['n']} |")

    if s["low"] > BENCHMARK:
        verdict = f"the whole 95% interval is above the average SUS score of {BENCHMARK:.0f}"
    elif s["high"] < BENCHMARK:
        verdict = f"the whole 95% interval is below the average SUS score of {BENCHMARK:.0f}"
    else:
        verdict = f"the 95% interval includes the average SUS score of {BENCHMARK:.0f}, so with {s['n']} students we cannot say it is above or below average"
    print(f"\nMean {s['mean']:.1f}; {verdict}.\n")

    print("### Item means (1 = strongly disagree, 5 = strongly agree)\n")
    print("Odd items are positive (higher is better), even items negative (lower is better).\n")
    print("| Item | Mean | Better when |")
    print("|---|---|---|")
    for i in range(10):
        values = [answers[i] for _, answers in people]
        print(f"| Q{i + 1} | {mean(values):.2f} | {'high' if i % 2 == 0 else 'low'} |")
    print()


def report_tasks(path: str) -> None:
    with open(path, newline="", encoding="utf-8-sig") as f:
        rows = [r for r in csv.DictReader(f) if (r.get("participant") or "").strip()]
    by_task: dict[str, list[dict]] = defaultdict(list)
    for row in rows:
        by_task[row["task"].strip().upper()].append(row)

    print("## Task results\n")
    print("| Task | Students | Alone | With help | Failed | Success rate (alone) | Median time (s) | Errors (mean) |")
    print("|---|---|---|---|---|---|---|---|")
    for task in sorted(by_task, key=lambda t: int(re.sub(r"\D", "", t) or 0)):
        items = by_task[task]
        results = [r["result"].strip().lower() for r in items]
        times = [float(r["seconds"]) for r in items if (r.get("seconds") or "").strip()]
        errors = [float(r["errors"]) for r in items if (r.get("errors") or "").strip()]
        alone = results.count("success")
        print(f"| {task} | {len(items)} | {alone} | {results.count('help')} | {results.count('fail')} "
              f"| {alone / len(items):.0%} | {median(times):.0f} | {mean(errors) if errors else 0:.1f} |")

    hard = [(r["participant"], r["task"], r["notes"]) for r in rows if r["result"].strip().lower() != "success" and (r.get("notes") or "").strip()]
    if hard:
        print("\n### Where students needed help or failed\n")
        for participant, task, notes in hard:
            print(f"- {task} ({participant}): {notes}")
    print()


def main(argv: list[str]) -> None:
    if len(argv) < 2:
        raise SystemExit(__doc__)
    report_sus(read_responses(argv[1]))
    if len(argv) > 2:
        report_tasks(argv[2])


if __name__ == "__main__":
    main(sys.argv)
