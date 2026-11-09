"""Synthetic campus lending world.

Why this is not circular: the trust rules are never used here. Each simulated
student has a hidden reliability and a behavior type, and whether a loan
defaults depends only on those hidden values, the loan size and a seasonal
shock (exam period). The rules and the model both only see the observable
history afterwards, and are judged on how well they guess the hidden outcome.

Behavior types (bad actors are injected at known rates):
  reliable   repays on time with high probability
  flaky      often late, sometimes defaults
  newcomer   joins late, little history
  strategic  repays slowly growing loans from many lenders to build a
             score, then defaults on one last, slightly larger loan
  ring       groups of 3 that trade fast fake loans, 5-star sales and mutual
             vouches, then members borrow at the basic-tier limit ($15-25)
             from outsiders and default
"""

from __future__ import annotations

from collections import defaultdict
from dataclasses import dataclass, field

import numpy as np
import pandas as pd

from . import rules
from .features import extract
from .history import DAY, HOUR, BorrowedLoan, History

BAD_TYPES = ("strategic", "ring")


@dataclass
class WorldConfig:
    seed: int = 7
    students: int = 1500
    days: int = 240
    # Share of each behavior type. Rings are formed in groups of 3.
    mix: dict[str, float] = field(
        default_factory=lambda: {"reliable": 0.55, "flaky": 0.20, "newcomer": 0.10, "strategic": 0.08, "ring": 0.07}
    )
    reliable_beta: tuple[float, float] = (18.0, 1.5)
    flaky_beta: tuple[float, float] = (4.0, 3.0)
    newcomer_beta: tuple[float, float] = (8.0, 2.0)
    # Exam season: defaults are more likely for everyone, and no feature shows it.
    season: tuple[int, int] = (100, 130)
    season_factor: float = 1.6
    amount_scale: float = 1.0


@dataclass
class Student:
    id: int
    type: str
    reliability: float
    join_day: int
    loans_per_month: float
    friends: list[int] = field(default_factory=list)
    ring: list[int] = field(default_factory=list)
    exit_day: int = 0
    exited: bool = False
    repaid_small: int = 0


@dataclass
class Loan:
    id: int
    borrower: int
    lender: int
    amount: float
    activated_at: int
    due_at: int
    repaid_at: int | None
    defaulted: bool
    kind: str  # honest, ring_internal, exit (bad actor cashing out), strategic_build


class World:
    def __init__(self, config: WorldConfig):
        self.config = config
        self.rng = np.random.default_rng(config.seed)
        self.students: list[Student] = []
        self.loans: list[Loan] = []
        self.borrowed_by: dict[int, list[Loan]] = defaultdict(list)
        self.lent_by: dict[int, list[Loan]] = defaultdict(list)
        self.vouches_for: dict[int, list[tuple[int, int]]] = defaultdict(list)  # vouchee -> [(voucher, day)]
        self.sales_by: dict[int, list[dict]] = defaultdict(list)
        self.requests: list[tuple[Loan, int]] = []  # (loan, day requested)

    # ---- population -------------------------------------------------

    def _populate(self) -> None:
        cfg, rng = self.config, self.rng
        types = list(cfg.mix)
        counts = {t: int(round(cfg.mix[t] * cfg.students)) for t in types}
        counts["ring"] -= counts["ring"] % 3

        next_id = 1
        for kind in types:
            for _ in range(counts[kind]):
                if kind == "reliable":
                    r = rng.beta(*cfg.reliable_beta)
                elif kind in ("flaky", "ring"):
                    r = rng.beta(*cfg.flaky_beta)
                elif kind == "newcomer":
                    r = rng.beta(*cfg.newcomer_beta)
                else:  # strategic: behaves well until it cashes out
                    r = rng.beta(*cfg.reliable_beta)
                join = int(rng.integers(150, 220)) if kind == "newcomer" else int(rng.integers(0, 120))
                rate = float(rng.uniform(0.6, 2.5)) if kind != "ring" else float(rng.uniform(3, 6))
                self.students.append(Student(next_id, kind, float(r), join, rate))
                next_id += 1

        ids = [s.id for s in self.students]
        by_id = self.by_id = {s.id: s for s in self.students}

        for s in self.students:
            s.friends = [int(f) for f in rng.choice(ids, size=6, replace=False) if f != s.id]

        ring_members = [s for s in self.students if s.type == "ring"]
        for i in range(0, len(ring_members), 3):
            group = ring_members[i : i + 3]
            start = max(m.join_day for m in group)
            exit_day = start + int(rng.integers(30, 80))
            for m in group:
                m.ring = [g.id for g in group if g.id != m.id]
                m.exit_day = exit_day
                for other in m.ring:
                    self.vouches_for[other].append((m.id, start + 1))

        for s in self.students:
            if s.type == "strategic":
                s.exit_day = s.join_day + int(rng.integers(45, 110))

        # Honest vouches: a couple of friends, sometimes returned.
        for s in self.students:
            if s.type == "ring":
                continue
            for friend in rng.choice(s.friends, size=min(2, len(s.friends)), replace=False):
                day = s.join_day + int(rng.integers(3, 40))
                self.vouches_for[int(friend)].append((s.id, day))
                if rng.random() < 0.3 and by_id[int(friend)].type != "ring":
                    self.vouches_for[s.id].append((int(friend), day + 2))

    # ---- simulation -------------------------------------------------

    def _amount(self, low: float, high: float) -> float:
        value = float(np.exp(self.rng.uniform(np.log(low), np.log(high)))) * self.config.amount_scale
        return float(min(100, max(5, round(value))))

    def _random_lender(self, borrower: Student, day: int) -> int:
        while True:
            candidate = self.students[int(self.rng.integers(0, len(self.students)))]
            if candidate.id != borrower.id and candidate.join_day <= day and candidate.id not in borrower.ring:
                return candidate.id

    def _open_loans(self, student_id: int, now: int) -> int:
        return sum(
            1
            for loan in self.borrowed_by[student_id]
            if loan.activated_at <= now and (loan.repaid_at is None or loan.repaid_at > now) and not loan.defaulted
        )

    def _make_loan(self, borrower: Student, lender: int, amount: float, day: int, p_default: float, kind: str) -> None:
        rng = self.rng
        activated = day * DAY + int(rng.integers(8, 20)) * HOUR
        due_days = int(rng.integers(7, 31))
        due = activated + due_days * DAY
        defaulted = rng.random() < p_default

        if defaulted:
            repaid = None
        elif kind == "ring_internal":
            repaid = activated + int(rng.integers(2, 60)) * HOUR
        else:
            late_p = (1 - borrower.reliability) * 0.9 if borrower.type not in ("strategic",) else 0.02
            if rng.random() < late_p:
                repaid = due + int(rng.integers(1, 13)) * DAY
            else:
                repaid = activated + int(rng.uniform(2 * DAY, due - activated))

        loan = Loan(len(self.loans) + 1, borrower.id, lender, amount, activated, due, repaid, bool(defaulted), kind)
        self.loans.append(loan)
        self.borrowed_by[borrower.id].append(loan)
        self.lent_by[lender].append(loan)
        self.requests.append((loan, day))

    def run(self) -> "World":
        self._populate()
        cfg, rng = self.config, self.rng

        for day in range(cfg.days):
            season = cfg.season_factor if cfg.season[0] <= day < cfg.season[1] else 1.0
            now = day * DAY

            for s in self.students:
                if day < s.join_day:
                    continue

                # Occasional sales.
                if s.type == "ring" and rng.random() < 0.08:
                    buyer = int(rng.choice(s.ring))
                    self.sales_by[s.id].append({"buyer_id": buyer, "stars": 5, "sold_at": now})
                elif rng.random() < 0.01:
                    buyer = self._random_lender(s, day)
                    stars = int(np.clip(round(1 + 4 * s.reliability + rng.normal(0, 0.7)), 1, 5))
                    self.sales_by[s.id].append({"buyer_id": buyer, "stars": stars, "sold_at": now})

                if rng.random() >= s.loans_per_month / 30 or self._open_loans(s.id, now) >= 2:
                    continue

                if s.type == "ring":
                    if day >= s.exit_day and not s.exited and rng.random() < 0.6:
                        # Cash out at the basic-tier limit, where the rules let them borrow.
                        s.exited = True
                        self._make_loan(s, self._random_lender(s, day), self._amount(15, 25), day, 0.8, "exit")
                    elif not s.exited:
                        self._make_loan(s, int(rng.choice(s.ring)), self._amount(5, 10), day, 0.0, "ring_internal")
                    continue

                if s.type == "strategic":
                    # Ramp up slowly so the final loan does not look like a jump.
                    built = sum(1 for l in self.borrowed_by[s.id] if l.repaid_at is not None)
                    step = min(100.0, 5 * 1.5**built)
                    if day >= s.exit_day and not s.exited:
                        s.exited = True
                        self._make_loan(s, self._random_lender(s, day), self._amount(step, min(100, step * 1.5)), day, 0.85, "exit")
                    elif not s.exited:
                        self._make_loan(s, self._random_lender(s, day), self._amount(max(5, step * 0.7), step), day, 0.02, "strategic_build")
                    continue

                lender = int(rng.choice(s.friends)) if rng.random() < 0.5 else self._random_lender(s, day)
                if self.by_id[lender].join_day > day:
                    lender = self._random_lender(s, day)
                history_len = len(self.borrowed_by[s.id])
                amount = self._amount(5, 25 if history_len < 3 else 80)
                p_default = min(0.95, (1 - s.reliability) * 0.6 * (0.6 + 0.8 * amount / 100) * season)
                self._make_loan(s, lender, amount, day, p_default, "honest")

        return self

    # ---- observation ------------------------------------------------

    def _circular(self, student_id: int, now: int) -> bool:
        since = now - 90 * DAY
        lent_to = {l.borrower for l in self.lent_by[student_id] if since <= l.activated_at < now}
        borrowed_from = {l.lender for l in self.borrowed_by[student_id] if since <= l.activated_at < now}
        if lent_to & borrowed_from:
            return True
        for middle in lent_to:
            for loan in self.lent_by[middle]:
                if since <= loan.activated_at < now and loan.borrower in borrowed_from:
                    return True
        return False

    def _has_defaulted(self, student_id: int, now: int) -> bool:
        return any(l.defaulted and l.due_at + 14 * DAY <= now for l in self.borrowed_by[student_id])

    def history_at(self, student: Student, now: int) -> History:
        vouchers = {v for v, d in self.vouches_for[student.id] if d * DAY <= now}
        vouched_back = {
            vouchee for vouchee, entries in self.vouches_for.items() for voucher, d in entries if voucher == student.id and d * DAY <= now
        } if vouchers else set()

        return History(
            now=now,
            account_created_at=student.join_day * DAY,
            borrowed=[
                BorrowedLoan(l.lender, l.amount, l.activated_at, l.due_at, l.repaid_at)
                for l in self.borrowed_by[student.id]
                if l.activated_at < now
            ],
            lent=[{"borrower_id": l.borrower, "activated_at": l.activated_at} for l in self.lent_by[student.id] if l.activated_at < now],
            vouches=[
                {"voucher_id": v, "mutual": v in vouched_back, "eligible": not self._has_defaulted(v, now)} for v in sorted(vouchers)
            ],
            sales=[sale for sale in self.sales_by[student.id] if sale["sold_at"] < now],
            circular_lending=self._circular(student.id, now),
        )

    def dataset(self) -> pd.DataFrame:
        """One row per loan request: features known at request time, the rule score, and the hidden outcome."""
        rows = []
        for loan, day in self.requests:
            student = self.by_id[loan.borrower]
            now = loan.activated_at
            history = self.history_at(student, now)
            feats = extract(history, loan.amount)
            rule = rules.score(history)
            rows.append(
                {
                    **feats,
                    "rule_score": rule.score,
                    "rule_max_loan": rule.max_loan,
                    "defaulted": int(loan.defaulted),
                    "student_id": student.id,
                    "student_type": student.type,
                    "loan_kind": loan.kind,
                    "bad_actor_exit": int(loan.kind == "exit"),
                    "day": day,
                }
            )
        return pd.DataFrame(rows)


def generate(config: WorldConfig | None = None) -> pd.DataFrame:
    return World(config or WorldConfig()).run().dataset()


def shifted(seed: int = 99) -> WorldConfig:
    """A different world for the robustness test: twice the bad actors, worse students, bigger loans."""
    return WorldConfig(
        seed=seed,
        mix={"reliable": 0.45, "flaky": 0.15, "newcomer": 0.10, "strategic": 0.16, "ring": 0.14},
        reliable_beta=(12.0, 2.0),
        flaky_beta=(3.0, 3.0),
        season=(60, 100),
        season_factor=2.0,
        amount_scale=1.3,
    )


def without(kind: str, seed: int = 7) -> WorldConfig:
    """The base world with one behavior type removed, to test on a type the model never saw."""
    base = WorldConfig(seed=seed)
    mix = dict(base.mix)
    removed = mix.pop(kind)
    mix["reliable"] += removed
    mix[kind] = 0.0
    return WorldConfig(seed=seed, mix=mix)
