import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { mockConfig, mockFamily, mockHome, mockInbox, mockMe, mockNews, mockSessions } from './mock';
import type { AppConfig, Child, HomeData, InboxItem, Me, NewsItem, Session } from './types';

const BASE = process.env.EXPO_PUBLIC_API_BASE?.replace(/\/$/, '');

/** No API configured: the app runs on bundled sample data. */
export const isMock = !BASE;

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
  }
}

let token: string | null = null;
let onUnauthorized: (() => void) | null = null;

export function setToken(value: string | null) {
  token = value;
}

export function setUnauthorizedHandler(handler: (() => void) | null) {
  onUnauthorized = handler;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
    // Some Apache setups strip Authorization before PHP sees it; the server accepts either.
    headers['X-WA-Token'] = token;
  }
  const res = await fetch(`${BASE}${path}`, { ...init, headers: { ...headers, ...(init.headers as object) } });
  if (res.status === 401 && token) onUnauthorized?.();
  const body = await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(res.status, body?.message ?? 'Something went wrong. Please try again.');
  return body as T;
}

const later = <T,>(value: T, ms = 250) => new Promise<T>((resolve) => setTimeout(() => resolve(value), ms));

export const api = {
  async login(email: string, password: string): Promise<{ token: string }> {
    if (isMock) return later({ token: 'sample-token' }, 600);
    return request('/wa/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        device: Device.modelName ?? 'Phone',
        platform: Platform.OS,
        app_version: Constants.expoConfig?.version ?? '',
      }),
    });
  },
  async logout(): Promise<void> {
    if (isMock) return;
    await request('/wa/v1/auth/logout', { method: 'POST' });
  },
  /** Single-use link that opens a website page already signed in. */
  async handoff(path: string): Promise<{ url: string }> {
    return request('/wa/v1/auth/handoff', { method: 'POST', body: JSON.stringify({ path }) });
  },
  async config(): Promise<AppConfig> {
    return isMock ? later(mockConfig) : request('/wa/v1/config');
  },
  async me(): Promise<Me> {
    return isMock ? later(mockMe) : request('/was/v1/me');
  },
  async home(): Promise<HomeData> {
    return isMock ? later(mockHome) : request('/wa/v1/content/home');
  },
  async news(filter: 'all' | 'mine' | 'club' | 'adults'): Promise<NewsItem[]> {
    return isMock ? later(mockNews(filter)) : request(`/wa/v1/content/news?filter=${filter}`);
  },
  async family(): Promise<Child[]> {
    return isMock ? later(mockFamily) : request('/was/v1/family');
  },
  async inbox(): Promise<InboxItem[]> {
    return isMock ? later(mockInbox) : request('/was/v1/inbox');
  },
  async markRead(id: number): Promise<void> {
    if (isMock) return;
    await request(`/was/v1/inbox/${id}/read`, { method: 'POST' });
  },
  async sessions(): Promise<Session[]> {
    return isMock ? later(mockSessions) : request('/wa/v1/auth/sessions');
  },
  /** Sign out one of this account's other phones. */
  async endSession(id: number): Promise<void> {
    if (isMock) return later(undefined);
    await request(`/wa/v1/auth/sessions/${id}`, { method: 'DELETE' });
  },
  async registerDevice(expoPushToken: string, platform: string): Promise<void> {
    if (isMock) return;
    await request('/wa/v1/devices', { method: 'POST', body: JSON.stringify({ expoPushToken, platform }) });
  },
  async requestDeletion(password: string): Promise<void> {
    if (isMock) return later(undefined, 600);
    await request('/was/v1/me/delete-request', { method: 'POST', body: JSON.stringify({ password }) });
  },
};
