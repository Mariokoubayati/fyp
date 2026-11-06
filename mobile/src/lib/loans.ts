import { Loan, LoanStatus, TrustFlag, TrustTier } from '../api/types';

export const STATUS_LABELS: Record<LoanStatus, string> = {
  requested: 'Looking for a lender',
  accepted: 'Waiting for handover',
  active: 'Active',
  repaid: 'Repaid',
  overdue: 'Overdue',
  defaulted: 'Defaulted',
  cancelled: 'Cancelled',
};

export const TIER_LABELS: Record<TrustTier, string> = {
  restricted: 'Restricted',
  basic: 'Basic',
  trusted: 'Trusted',
};

/** Why the tier is held back, in words a student understands. */
export const FLAG_MESSAGES: Record<TrustFlag, string> = {
  unverified_email: 'Verify your university email to start borrowing.',
  new_account: 'New accounts stay at Basic for their first 14 days.',
  circular_lending:
    'Loans went back and forth within a small circle, so your tier stays at Basic for now.',
};

/** Which action the signed-in student can take next, if any. */
export type NextLoanStep =
  | { kind: 'accept' }
  | { kind: 'confirm-handover'; waitingForOther: boolean; alreadyConfirmed: boolean }
  | { kind: 'confirm-repayment'; alreadyConfirmed: boolean }
  | null;

export function nextStep(loan: Loan): NextLoanStep {
  const role = loan.my_role;

  if (loan.status === 'requested' && role === null) {
    return { kind: 'accept' };
  }

  if (loan.status === 'accepted' && role) {
    const mine = loan.handover[`${role}_confirmed`];
    const theirs = loan.handover[`${role === 'borrower' ? 'lender' : 'borrower'}_confirmed`];

    return { kind: 'confirm-handover', alreadyConfirmed: mine, waitingForOther: mine && !theirs };
  }

  if (['active', 'overdue', 'defaulted'].includes(loan.status) && role) {
    return { kind: 'confirm-repayment', alreadyConfirmed: loan.repayment[`${role}_confirmed`] };
  }

  return null;
}
