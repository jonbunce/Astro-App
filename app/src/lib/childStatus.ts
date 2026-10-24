import type { Child } from '@/api/types';

/** How subs are paid when nothing is collected by Direct Debit, or null when they are. */
export function subsNote(child: Child) {
  if (child.paymentType === 'exempt') return 'Exempt from subs';
  if (child.paymentType === 'team_direct') return 'Subs paid to the team';
  return null;
}

/** The short status shown on a child's card and details. */
export function childStatusLabel(child: Child) {
  if (child.status === 'inactive') return 'Direct Debit cancelled';
  if (child.status === 'pending') return 'Pending';
  if (child.status === 'withdrawn') return 'Withdrawn';
  return 'Active';
}
