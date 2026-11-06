"""The shared history format.

A student's history at a moment in time, in the same JSON shape Laravel sends.
Times are Unix seconds. Only loans where the cash changed hands are listed.

{
  "now": 1762400000,
  "account_created_at": 1757200000,
  "email_verified": true,
  "borrowed": [{"lender_id": 7, "amount": 20, "activated_at": ..., "due_at": ..., "repaid_at": null}],
  "lent": [{"borrower_id": 9, "activated_at": ...}],
  "vouches": [{"voucher_id": 3, "mutual": true, "eligible": true}],
  "sales": [{"buyer_id": 4, "stars": 5, "sold_at": ...}],
  "circular_lending": false
}

`eligible` is false for vouches from unverified or sub-400 students, and
`circular_lending` comes from the backend's CollusionDetector (a ring of three
cannot be seen from one student's own history).
"""

from __future__ import annotations

from dataclasses import dataclass, field

HOUR = 3600
DAY = 24 * HOUR

# Same as the backend: a loan defaults 14 days after it goes late.
DEFAULT_AFTER_DAYS = 14


@dataclass
class BorrowedLoan:
    lender_id: int
    amount: float
    activated_at: int
    due_at: int
    repaid_at: int | None = None

    def outcome(self, now: int) -> str:
        """What is known about this loan at `now`: on_time, late, overdue, defaulted or open."""
        if self.repaid_at is not None and self.repaid_at <= now:
            return "on_time" if self.repaid_at <= self.due_at else "late"
        if now >= self.due_at + DEFAULT_AFTER_DAYS * DAY:
            return "defaulted"
        if now > self.due_at:
            return "overdue"
        return "open"


@dataclass
class History:
    now: int
    account_created_at: int
    email_verified: bool = True
    borrowed: list[BorrowedLoan] = field(default_factory=list)
    lent: list[dict] = field(default_factory=list)
    vouches: list[dict] = field(default_factory=list)
    sales: list[dict] = field(default_factory=list)
    circular_lending: bool = False

    @classmethod
    def from_dict(cls, data: dict) -> "History":
        return cls(
            now=int(data["now"]),
            account_created_at=int(data["account_created_at"]),
            email_verified=bool(data.get("email_verified", True)),
            borrowed=[
                BorrowedLoan(
                    lender_id=int(loan["lender_id"]),
                    amount=float(loan["amount"]),
                    activated_at=int(loan["activated_at"]),
                    due_at=int(loan["due_at"]),
                    repaid_at=None if loan.get("repaid_at") is None else int(loan["repaid_at"]),
                )
                for loan in data.get("borrowed", [])
            ],
            lent=list(data.get("lent", [])),
            vouches=list(data.get("vouches", [])),
            sales=list(data.get("sales", [])),
            circular_lending=bool(data.get("circular_lending", False)),
        )

    def to_dict(self) -> dict:
        return {
            "now": self.now,
            "account_created_at": self.account_created_at,
            "email_verified": self.email_verified,
            "borrowed": [loan.__dict__.copy() for loan in self.borrowed],
            "lent": self.lent,
            "vouches": self.vouches,
            "sales": self.sales,
            "circular_lending": self.circular_lending,
        }
