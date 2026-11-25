"""Synthetic campus for evaluating the recommender.

Students choose groups and listings from HIDDEN reasons the recommender can
never see directly:
  - friendships (it only sees who they were in groups with before)
  - a preferred time of day (it only sees the hours of past groups)
  - interest in some listing categories and price sensitivity
plus being enrolled in the course, which both the baseline and the model see.

The baseline is today's SQL logic: groups in your courses ordered by number of
classmates, and the newest listings first.

Events and projects run afterwards on their own random stream, so adding them
does not change the groups and listings above. Students pick events from hidden
tastes per topic and from friends who are going; they ask to join projects for
their real skills, their tastes and their friends. The recommender only sees
what a student typed on their profile (often nothing, or only part of the truth)
and their traces: who they met before, which events they went to and when.
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


TAGS = ["academic", "tech", "sports", "social", "culture", "arts", "career", "volunteering"]
MAJOR_TAGS = {
    "CS": ["tech", "academic", "career"],
    "Business": ["career", "social", "volunteering"],
    "Engineering": ["tech", "academic", "sports"],
    "Design": ["arts", "culture", "social"],
}
MAJOR_SKILLS = {
    "CS": ["Laravel", "Python", "React Native", "SQL", "Machine learning"],
    "Business": ["Marketing", "Excel", "Public speaking", "Finance"],
    "Engineering": ["CAD", "MATLAB", "Arduino", "Python"],
    "Design": ["UI design", "Figma", "Illustration", "Video editing"],
}
GENERAL_SKILLS = ["Writing", "Photography", "Event planning"]
PROJECT_DAYS = 30  # owners stop looking after about a month


@dataclass
class HubProfile:
    tastes: dict[str, float]          # hidden: how much the student likes each topic
    interests: list[str]              # what they typed on their profile
    skills: list[str]                 # hidden: what they can really do
    stated_skills: list[str]          # what they typed on their profile


@dataclass
class CampusEvent:
    id: int
    creator: int
    tags: list[str]
    course: int | None
    created_at: int
    starts_at: int
    capacity: int | None
    attendees: list[tuple[int, int]]  # (student id, said yes at)


@dataclass
class Project:
    id: int
    owner: int
    tags: list[str]
    skills_needed: list[str]
    course: int | None
    team_size: int
    created_at: int
    members: list[tuple[int, int]]    # accepted (student id, at)
    asked: set[int] = field(default_factory=set)


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
        self.hub_rng = np.random.default_rng(config.seed + 10_000)
        self.hub: dict[int, HubProfile] = {}
        self.events: list[CampusEvent] = []
        self.projects: list[Project] = []
        self.rsvps: list[tuple[int, int, int]] = []     # (student, event id, time)
        self.requests: list[tuple[int, int, int]] = []  # (student, project id, time)

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

        self._run_hub()
        return self

    # ---- events and projects (separate random stream) -----------------

    def _populate_hub(self) -> None:
        rng = self.hub_rng
        for s in self.students.values():
            tastes = {t: float(rng.beta(1.2, 3.5)) for t in TAGS}
            for t in MAJOR_TAGS[s.major]:
                tastes[t] = min(1.0, tastes[t] + float(rng.uniform(0.1, 0.4)))
            # The profile is filled by about 70%, and then only with the strongest tastes, imperfectly.
            interests = []
            if rng.random() < 0.7:
                interests = [t for t in TAGS if rng.random() < self._sigmoid(8 * (tastes[t] - 0.45))][:4]
            pool = MAJOR_SKILLS[s.major]
            skills = list(rng.choice(pool, size=int(rng.integers(1, 4)), replace=False))
            if rng.random() < 0.3:
                skills.append(str(rng.choice(GENERAL_SKILLS)))
            stated = list(skills) if rng.random() < 0.65 else (skills[:1] if rng.random() < 0.4 else [])
            self.hub[s.id] = HubProfile(tastes, interests, [str(k) for k in skills], [str(k) for k in stated])

    def _pick_tags(self, sid: int, k: int) -> list[str]:
        tastes = self.hub[sid].tastes
        weights = np.array([tastes[t] for t in TAGS]) + 0.05
        return [TAGS[int(i)] for i in self.hub_rng.choice(len(TAGS), size=k, replace=False, p=weights / weights.sum())]

    def _event_hour(self, tags: list[str]) -> int:
        rng = self.hub_rng
        if "academic" in tags or "career" in tags:
            return int(rng.integers(10, 17))
        if "sports" in tags or "social" in tags:
            return int(rng.integers(17, 22))
        return int(rng.integers(12, 21))

    def _run_hub(self) -> None:
        self._populate_hub()
        rng = self.hub_rng
        ids = list(self.students)

        for day in range(self.config.days):
            now = day * DAY

            # New events: a few a day, by students, about what they like.
            for _ in range(int(rng.poisson(self.config.students / 200))):
                creator = int(rng.choice(ids))
                tags = self._pick_tags(creator, int(rng.integers(1, 3)))
                course = int(rng.choice(self.students[creator].courses)) if "academic" in tags and rng.random() < 0.7 else None
                starts = (day + int(rng.integers(2, 15))) * DAY + self._event_hour(tags) * 3600
                capacity = None if rng.random() < 0.6 else int(rng.integers(15, 81))
                self.events.append(CampusEvent(len(self.events) + 1, creator, tags, course, now, starts, capacity, [(creator, now)]))

            # New projects: about one a day, needing the owner's skills and some from other majors.
            for _ in range(int(rng.poisson(self.config.students / 600))):
                owner = int(rng.choice(ids))
                me = self.students[owner]
                needed = list(rng.choice(MAJOR_SKILLS[me.major], size=int(rng.integers(1, 3)), replace=False))
                if rng.random() < 0.6:
                    other = [m for m in MAJOR_SKILLS if m != me.major][int(rng.integers(0, 3))]
                    needed.append(str(rng.choice(MAJOR_SKILLS[other])))
                tags = self._pick_tags(owner, int(rng.integers(1, 3)))
                course = int(rng.choice(me.courses)) if rng.random() < 0.3 else None
                self.projects.append(Project(len(self.projects) + 1, owner, tags, [str(k) for k in needed], course,
                                             int(rng.integers(3, 7)), now, [(owner, now)]))

            # Saying yes to events.
            open_events = [e for e in self.events if e.created_at <= now < e.starts_at
                           and (e.capacity is None or len(e.attendees) < e.capacity)]
            for s in self.students.values():
                if rng.random() > 0.12 or not open_events:
                    continue
                profile = self.hub[s.id]
                sample = [open_events[int(i)] for i in rng.choice(len(open_events), size=min(30, len(open_events)), replace=False)]
                best, best_u = None, -9.0
                for e in sample:
                    if e.creator == s.id or any(a == s.id for a, _ in e.attendees):
                        continue
                    hour = (e.starts_at // 3600) % 24
                    friends = sum(1 for a, _ in e.attendees if a in s.friends)
                    utility = 2.8 * float(np.mean([profile.tastes[t] for t in e.tags])) + 1.4 * min(2, friends)
                    utility += 0.9 * (e.course is not None and e.course in s.courses)
                    utility -= 0.12 * min(abs(hour - s.preferred_hour), 24 - abs(hour - s.preferred_hour))
                    utility += rng.gumbel(0, 0.6)
                    if utility > best_u:
                        best, best_u = e, utility
                if best is not None and rng.random() < self._sigmoid(best_u - 2.6):
                    best.attendees.append((s.id, now))
                    self.rsvps.append((s.id, best.id, now))

            # Asking to join projects; owners accept most requests while there is room.
            open_projects = [p for p in self.projects if p.created_at <= now < p.created_at + PROJECT_DAYS * DAY and len(p.members) < p.team_size]
            for s in self.students.values():
                if rng.random() > 0.015 or not open_projects:
                    continue
                profile = self.hub[s.id]
                skills = {k.lower() for k in profile.skills}
                best, best_u = None, -9.0
                for p in open_projects:
                    if p.owner == s.id or s.id in p.asked:
                        continue
                    match = len(skills & {k.lower() for k in p.skills_needed}) / len(p.skills_needed)
                    utility = 2.6 * match + 1.6 * float(np.mean([profile.tastes[t] for t in p.tags]))
                    utility += 1.2 * (p.owner in s.friends) + 0.6 * sum(1 for m, _ in p.members if m in s.friends)
                    utility += 0.5 * (p.course is not None and p.course in s.courses)
                    utility += rng.gumbel(0, 0.6)
                    if utility > best_u:
                        best, best_u = p, utility
                if best is not None and rng.random() < self._sigmoid(best_u - 3.0):
                    best.asked.add(s.id)
                    self.requests.append((s.id, best.id, now))
                    if rng.random() < 0.8 and len(best.members) < best.team_size:
                        best.members.append((s.id, now))

    def _crowds_of(self, sid: int) -> list[list[tuple[int, int]]]:
        """Study groups, events and project teams the student was ever part of (built once, after the simulation)."""
        if not hasattr(self, "_crowd_index"):
            index: dict[int, list[list[tuple[int, int]]]] = {}
            for crowd in [*(g.members for g in self.groups), *(e.attendees for e in self.events), *(p.members for p in self.projects)]:
                for m in {m for m, _ in crowd}:
                    index.setdefault(m, []).append(crowd)
            self._crowd_index = index
        return self._crowd_index.get(sid, [])

    def _met_before(self, sid: int, now: int) -> dict[int, int]:
        """Students met in groups, events and project teams before `now`: what Laravel calls campus peers."""
        met: dict[int, int] = {}
        for crowd in self._crowds_of(sid):
            if any(m == sid and t < now for m, t in crowd):
                for m, t in crowd:
                    if m != sid and t < now:
                        met[m] = met.get(m, 0) + 1
        return met

    def event_user(self, sid: int, now: int) -> dict:
        s, profile = self.students[sid], self.hub[sid]
        went = [self.events[eid - 1] for s_, eid, t in self._rsvps_of(sid) if t < now]
        return {
            "id": sid, "major": s.major, "interests": profile.interests, "course_ids": s.courses,
            "co_members": self._met_before(sid, now),
            "past_event_tags": [t for e in went for t in e.tags],
            "past_event_count": len(went),
            "past_event_hours": [(e.starts_at // 3600) % 24 for e in went],
        }

    def _rsvps_of(self, sid: int) -> list[tuple[int, int, int]]:
        if not hasattr(self, "_rsvp_index"):
            index: dict[int, list[tuple[int, int, int]]] = {}
            for r in self.rsvps:
                index.setdefault(r[0], []).append(r)
            self._rsvp_index = index
        return self._rsvp_index.get(sid, [])

    def event_payload(self, e: CampusEvent, now: int) -> dict:
        return {
            "id": e.id, "tags": e.tags, "course_id": e.course, "starts_at": e.starts_at, "capacity": e.capacity,
            "attendees": [{"id": a, "major": self.students[a].major} for a, t in e.attendees if t < now or a == e.creator],
        }

    def open_events_at(self, sid: int, now: int) -> list[CampusEvent]:
        return [
            e for e in self.events
            if e.created_at <= now < e.starts_at and e.creator != sid
            and (e.capacity is None or sum(1 for _, t in e.attendees if t < now) < e.capacity)
            and not any(a == sid and t < now for a, t in e.attendees)
        ]

    def project_user(self, sid: int, now: int) -> dict:
        s, profile = self.students[sid], self.hub[sid]
        return {"id": sid, "major": s.major, "interests": profile.interests, "skills": profile.stated_skills,
                "course_ids": s.courses, "co_members": self._met_before(sid, now)}

    def project_payload(self, p: Project, now: int) -> dict:
        return {
            "id": p.id, "owner_id": p.owner, "owner_major": self.students[p.owner].major, "tags": p.tags,
            "skills_needed": p.skills_needed, "course_id": p.course, "team_size": p.team_size,
            "member_ids": [m for m, t in p.members if t < now or m == p.owner], "created_at": p.created_at,
        }

    def open_projects_at(self, sid: int, now: int) -> list[Project]:
        asked_before = {pid for s, pid, t in self.requests if s == sid and t < now}
        return [
            p for p in self.projects
            if p.created_at <= now < p.created_at + PROJECT_DAYS * DAY and p.owner != sid and p.id not in asked_before
            and sum(1 for m, t in p.members if t < now or m == p.owner) < p.team_size
        ]

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
