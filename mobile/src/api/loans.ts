import { api } from './client';
import { ApiEnvelope, Loan, NewLoan, Paginated, TrustSummary } from './types';

export type LoanScope = 'open' | 'borrowed' | 'lent';

export async function fetchLoans(scope: LoanScope): Promise<Loan[]> {
  const { data } = await api.get<Paginated<Loan>>('/loans', { params: scope === 'open' ? undefined : { role: scope } });

  return data.data;
}

export async function fetchLoan(id: number): Promise<Loan> {
  const { data } = await api.get<ApiEnvelope<Loan>>(`/loans/${id}`);

  return data.data;
}

export async function requestLoan(payload: NewLoan): Promise<Loan> {
  const { data } = await api.post<ApiEnvelope<Loan>>('/loans', payload);

  return data.data;
}

type LoanAction = 'accept' | 'confirm-handover' | 'confirm-repayment' | 'cancel';

export async function actOnLoan(id: number, action: LoanAction): Promise<Loan> {
  const { data } = await api.post<ApiEnvelope<Loan>>(`/loans/${id}/${action}`);

  return data.data;
}

export async function fetchMyTrust(): Promise<TrustSummary> {
  const { data } = await api.get<ApiEnvelope<TrustSummary>>('/me/trust');

  return data.data;
}
