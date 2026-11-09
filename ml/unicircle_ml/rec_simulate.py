"""Synthetic campus for evaluating the recommender.

Students choose groups and listings from HIDDEN reasons the recommender can
never see directly:
  - friendships (it only sees who they were in groups with before)
  - a preferred time of day (it only sees the hours of past groups)
  - interest in some listing categories and price sensitivity
plus being enrolled in the course, which both the baseline and the model see.

The baseline is today's SQL logic: groups in your courses ordered by number of
classmates, and the newest listings first.
"""

from __future__ import annotations

from dataclasses import dataclass, field

import numpy as np

from .recommend import DAY

MAJORS = {
    "CS": ["Introduction to Programming", "Data Structures", "Object-Oriented Programming", "Database Systems",
           "Operating Systems", "Computer Networks", "Software Engineering", "Artificial Intelligence"],
    "Business": ["Principles of Management", "Financial Accounting", "Microeconomics", "Marketing Fundamentals",
                 "Business Statistics", "Corporate Finance", "Operations Management", "Business Strategy"],
    "Engineering": ["Calculus I", "Calculus II", "Physics for Engineers", "Linear Algebra",
                    "Circuit Analysis", "Thermodynamics", "Signals and Systems", "Control Systems"],
    "Design": ["Drawing Fundamentals", "Color Theory", "Typography", "Digital Imaging",
               "User Interface Design", "Motion Graphics", "Design History", "Portfolio Studio"],
}
SHARED = ["Academic Writing", "Probability and Statistics", "Discrete Mathematics", "Ethics in Technology"]

CATEGORIES = ["books", "notes", "electronics", "supplies", "other"]
TEMPLATES = {
    "books": ["{c} textbook", "{c} textbook latest edition", "Used {c} book", "{c} reference book"],
    "notes": ["{c} full semester notes", "{c} summary notes", "{c} exam notes and past papers"],
    "electronics": ["Scientific calculator", "USB drive 64GB", "Laptop stand", "Wireless mouse", "Graphics tablet"],
    "supplies": ["Lab coat", "Drawing kit", "Notebook bundle", "Engineering ruler set", "Sketchbook"],
    "other": ["Desk lamp", "Backpack", "Concert ticket", "Bike lock", "Mini fridge"],
}
BASE_PRICE = {"books": 25, "notes": 8, "electronics": 30, "supplies": 12, "other": 20}


@dataclass
class RecConfig:
    seed: int = 31
    students: int = 600
    days: int = 120
    cutoff_day: int = 90


@dataclass
class Student:
    id: int
    major: str
    year: int
    courses: list[int]
    preferred_hour: int
    interests: dict[str, float]
    price_sensitivity: float
    friends: set[int] = field(default_factory=set)


@dataclass
class Group:
    id: int
    course: int
    starts_at: int
    capacity: int
    members: list[tuple[int, int]]  # (student id, joined at)


@dataclass
class Listing:
    id: int
    seller: int
    course: int | None
    title: str
    category: str
    price: float
    created_at: int
    buyer: int | None = None
    sold_at: int | None = None


class Campus:
    def __init__(self, config: RecConfig):
        self.config = config
        self.rng = np.random.default_rng(config.seed)
        self.course_names: list[str] = []
        self.course_major: list[str | None] = []
        for major, names in MAJORS.items():
            for name in names:
                self.course_names.append(name)
                self.course_major.append(major)
        for name in SHARED:
            self.course_names.append(name)
            self.course_major.append(None)
        self.students: dict[int, Student] = {}
        self.groups: list[Group] = []
        self.listings: list[Listing] = []
        self.joins: list[tuple[int, int, int]] = []  # (student, group id, time)
        self.purchases: list[tuple[int, int, int]] = []  # (student, listing id, time)

    # ---- population -------------------------------------------------

    def _populate(self) -> None:
        rng = self.rng
        majors = list(MAJORS)
        shared_ids = [i for i, m in enumerate(self.course_major) if m is None]
        for sid in range(1, self.config.students + 1):
            major = majors[int(rng.integers(0, len(majors)))]
            year = int(rng.integers(1, 5))
            major_ids = [i for i, m in enumerate(self.course_major) if m == major]
            # Two courses at the student's level, two around it, one shared course.
            level = sorted(major_ids)[min(6, (year - 1) * 2) : min(8, (year - 1) * 2 + 2)]
            others = [c for c in major_ids if c not in level]
            courses = level + list(rng.choice(others, size=2, replace=False)) + [int(rng.choice(shared_ids))]
            interests = {c: float(rng.beta(1.2, 3)) for c in CATEGORIES}
            self.students[sid] = Student(
                sid, major, year, [int(c) for c in courses],
                preferred_hour=int(rng.integers(9, 21)),
                interests=interests,
                price_sensitivity=float(rng.uniform(0.3, 1.5)),
            )

        # Hidden friendships: small circles mostly inside the same major and year.
        ids = list(self.students)
        for sid in ids:
            me = self.students[sid]
            peers = [o for o in ids if o != sid and self.students[o].major == me.major and abs(self.students[o].year - me.year) <= 1]
            for friend in rng.choice(peers, size=min(len(peers), int(rng.integers(2, 6))), replace=False):
                me.friends.add(int(friend))
                self.students[int(friend)].friends.add(sid)

    # ---- simulation -------------------------------------------------

    def _sigmoid(self, x: float) -> float:
        return float(1 / (1 + np.exp(-x)))

    def run(self) -> "Campus":
        self._populate()
        rng = self.rng
        enrolled: dict[int, list[int]] = {}
        for s in self.students.values():
            for c in s.courses:
                enrolled.setdefault(c, []).append(s.id)

        for day in range(self.config.days):
            now = day * DAY

            # New groups: a student in the course creates one at a time that suits them.
            for course, students in enrolled.items():
                if rng.random() < 0.35:
                    creator = self.students[int(rng.choice(students))]
                    hour = int(np.clip(creator.preferred_hour + rng.integers(-1, 2), 8, 22))
                    starts = (day + int(rng.integers(2, 12))) * DAY + hour * 3600
                    group = Group(len(self.groups) + 1, course, starts, int(rng.integers(4, 9)), [(creator.id, now)])
                    self.groups.append(group)

            # New listings: students sell things for courses they take, or general items.
            for s in self.students.values():
                if rng.random() < 0.04:
                    category = CATEGORIES[int(rng.choice(5, p=[0.35, 0.25, 0.15, 0.12, 0.13]))]
                    course = int(rng.choice(s.courses)) if category in ("books", "notes") else None
                    template = TEMPLATES[category][int(rng.integers(0, len(TEMPLATES[category])))]
                    title = template.format(c=self.course_names[course]) if course is not None else template
                    price = float(round(BASE_PRICE[category] * rng.lognormal(0, 0.35)))
                    self.listings.append(Listing(len(self.listings) + 1, s.id, course, title, category, max(2.0, price), now))

            # Joining groups.
            open_groups = [g for g in self.groups if g.starts_at > now and len(g.members) < g.capacity]
            for s in self.students.values():
                if rng.random() > 0.5:
                    continue
                best, best_u = None, -9.0
                for g in open_groups:
                    if any(m == s.id for m, _ in g.members):
                        continue
                    in_course = g.course in s.courses
                    friends = sum(1 for m, _ in g.members if m in s.friends)
                    if not in_course and friends == 0:
                        continue
                    hour = (g.starts_at // 3600) % 24
                    utility = (1.2 if in_course else -1.0) + 1.8 * friends - 0.35 * min(abs(hour - s.preferred_hour), 24 - abs(hour - s.preferred_hour))
                    utility += rng.gumbel(0, 0.6)
                    if utility > best_u:
                        best, best_u = g, utility
                if best is not None and rng.random() < self._sigmoid(best_u - 1.0):
                    best.members.append((s.id, now))
                    self.joins.append((s.id, best.id, now))

            # Buying.
            available = [l for l in self.listings if l.buyer is None and l.created_at <= now]
            for s in self.students.values():
                if rng.random() > 0.25 or not available:
                    continue
                sample = [available[int(i)] for i in rng.choice(len(available), size=min(25, len(available)), replace=False)]
                best, best_u = None, -9.0
                for l in sample:
                    if l.seller == s.id:
                        continue
                    need = 1.5 if (l.course is not None and l.course in s.courses) else 0.0
                    utility = need + 2.2 * s.interests[l.category] + 0.9 * (l.seller in s.friends)
                    utility -= s.price_sensitivity * (l.price / BASE_PRICE[l.category] - 1)
                    utility += rng.gumbel(0, 0.5)
                    if utility > best_u:
                        best, best_u = l, utility
                if best is not None and rng.random() < self._sigmoid(best_u - 2.2):
                    best.buyer, best.sold_at = s.id, now
                    self.purchases.append((s.id, best.id, now))

        return self

    # ---- what Laravel would send, at time `now` ---------------------

    def group_user(self, sid: int, now: int) -> dict:
        s = self.students[sid]
        co: dict[int, int] = {}
        hours = []
        for g in self.groups:
            mine = [t for m, t in g.members if m == sid and t < now]
            if not mine:
                continue
            hours.append((g.starts_at // 3600) % 24)
            for m, t in g.members:
                if m != sid and t < now:
                    co[m] = co.get(m, 0) + 1
        return {"id": sid, "major": s.major, "study_year": s.year, "course_ids": s.courses, "co_members": co, "past_join_hours": hours}

    def group_payload(self, g: Group, now: int) -> dict:
        return {
            "id": g.id,
            "course_id": g.course,
            "starts_at": g.starts_at,
            "capacity": g.capacity,
            "members": [
                {"id": m, "major": self.students[m].major, "study_year": self.students[m].year, "course_ids": self.students[m].courses}
                for m, t in g.members
                if t < now
            ],
        }

    def open_groups_at(self, sid: int, now: int) -> list[Group]:
        return [
            g for g in self.groups
            if g.starts_at > now and sum(1 for _, t in g.members if t < now) < g.capacity
            and g.members[0][1] <= now and not any(m == sid and t < now for m, t in g.members)
        ]

    def listing_user(self, sid: int, now: int) -> dict:
        s = self.students[sid]
        bought = [l for l in self.listings if l.buyer == sid and l.sold_at is not None and l.sold_at < now]
        co = set(self.group_user(sid, now)["co_members"])
        return {
            "id": sid,
            "course_ids": s.courses,
            "course_names": [self.course_names[c] for c in s.courses],
            "purchases": [{"title": l.title, "category": l.category, "price": l.price} for l in bought],
            "co_member_ids": sorted(co),
        }

    def listing_payload(self, l: Listing) -> dict:
        return {"id": l.id, "title": l.title, "description": "", "category": l.category, "course_id": l.course,
                "price": l.price, "seller_id": l.seller, "created_at": l.created_at}

    def available_listings_at(self, sid: int, now: int) -> list[Listing]:
        return [l for l in self.listings if l.created_at <= now and (l.sold_at is None or l.sold_at >= now) and l.seller != sid]
