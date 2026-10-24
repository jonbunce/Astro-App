import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';

import { api, isMock } from '@/api/client';
import { queryClient } from '@/api/queryClient';
import { SITE_URL } from '@/theme';

/**
 * Open an ASTRO page on the website already signed in (registration, rejoin, Direct Debit,
 * editing a child). The browser session is ephemeral so nothing outlives app sign-out;
 * the website sends the user back via wombourneallstars://done when the flow finishes.
 */
export async function openSignedIn(path: string) {
  if (isMock) {
    await WebBrowser.openBrowserAsync(`${SITE_URL}${path}`);
    return;
  }
  const { url } = await api.handoff(path);
  await WebBrowser.openAuthSessionAsync(url, 'wombourneallstars://done', { preferEphemeralSession: true });
  // Whatever was changed on the website shows straight away back in the app.
  await queryClient.invalidateQueries();
}

/** Show a public club page (news article, info page) inside the app. */
export function openPage(url: string, title: string) {
  router.push({ pathname: '/web', params: { url, title } });
}

export function formatDate(iso: string, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short', year: 'numeric' }) {
  return new Date(iso).toLocaleDateString('en-GB', opts);
}

export function formatMoney(amount: number) {
  const sign = amount < 0 ? '−' : '';
  return `${sign}£${Math.abs(amount).toFixed(2)}`;
}

/** "16:05", "Yesterday", "3 days ago" or "19 Oct". */
export function relativeTime(iso: string, now = new Date()) {
  const d = new Date(iso);
  const days = Math.floor((new Date(now.toDateString()).getTime() - new Date(d.toDateString()).getTime()) / 86_400_000);
  if (days <= 0) return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  if (days === 1) return 'Yesterday';
  if (days < 7) return `${days} days ago`;
  return formatDate(iso, { day: 'numeric', month: 'short' });
}
