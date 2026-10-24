import type { Child } from '@/api/types';

/** The short status shown on a child's card and details. */
export function childStatusLabel(child: Child) {
  if (child.status === 'inactive') return 'Direct Debit cancelled';
  if (child.status === 'pending') return 'Pending';
  if (child.status === 'withdrawn') return 'Withdrawn';
  return 'Active';
}
