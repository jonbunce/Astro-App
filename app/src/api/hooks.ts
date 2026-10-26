import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from './client';
import type { InboxItem, Session } from './types';

export const useConfig = () => useQuery({ queryKey: ['config'], queryFn: api.config, staleTime: 60 * 60_000 });
export const useMe = () => useQuery({ queryKey: ['me'], queryFn: api.me });
export const useHome = () => useQuery({ queryKey: ['home'], queryFn: api.home });
export const useFamily = () => useQuery({ queryKey: ['family'], queryFn: api.family });
export const useInbox = () => useQuery({ queryKey: ['inbox'], queryFn: api.inbox });
export const useSessions = () => useQuery({ queryKey: ['sessions'], queryFn: api.sessions });

export const useNews = (filter: 'all' | 'mine' | 'club' | 'adults') =>
  useQuery({ queryKey: ['news', filter], queryFn: () => api.news(filter) });

export function useChild(id: number) {
  const family = useFamily();
  return { ...family, data: family.data?.find((c) => c.id === id) };
}

export function useUnreadCount() {
  const { data } = useInbox();
  return data?.filter((m) => !m.read).length ?? 0;
}

export function useMarkRead() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: api.markRead,
    onMutate: (id: number) => {
      qc.setQueryData<InboxItem[]>(['inbox'], (items) => items?.map((m) => (m.id === id ? { ...m, read: true } : m)));
    },
  });
}

export function useEndSession() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: api.endSession,
    onSuccess: (_, id: number) => {
      qc.setQueryData<Session[]>(['sessions'], (list) => list?.filter((s) => s.id !== id));
    },
  });
}
