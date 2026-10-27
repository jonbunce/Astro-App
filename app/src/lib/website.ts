import * as Linking from 'expo-linking';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';

import { api, isMock } from '@/api/client';
import { queryClient } from '@/api/queryClient';
import type { AppConfig, InfoLink } from '@/api/types';
import { SITE_URL } from '@/theme';

const SITE_HOST = SITE_URL.replace(/^https?:\/\/(www\.)?/i, '').replace(/\/.*$/, '');
const ON_SITE = new RegExp(`^https?://(www\\.)?${SITE_HOST.replace(/\./g, '\\.')}(/[^?#]*)?`, 'i');
const FILE = /\.(pdf|docx?|xlsx?|pptx?|odt|rtf)(\?|#|$)/i;

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

/** A More-tab club page: as text when the website sends it that way, otherwise the web page. */
export function openInfo(page: InfoLink) {
  if (page.native && page.slug) {
    router.push({ pathname: '/page/[slug]', params: { slug: page.slug, title: page.title } });
  } else {
    openPage(page.url, page.title);
  }
}

/**
 * Follow a link from a text page. Email and phone links open Mail or Phone; club pages the app
 * shows as text open as text; other club pages open inside the app; documents and other
 * websites open in the browser.
 */
export function openLink(href: string) {
  if (/^(mailto|tel):/i.test(href)) {
    void Linking.openURL(href);
    return;
  }
  const site = ON_SITE.exec(href);
  if (site && !FILE.test(href)) {
    const path = (site[2] ?? '/').replace(/\/+$/, '');
    const config = queryClient.getQueryData<AppConfig>(['config']);
    const page = [...(config?.clubPages ?? []), ...(config?.helpPages ?? [])].find((p) => p.slug && `/${p.slug}` === path);
    if (page) openInfo(page);
    else openPage(href, '');
    return;
  }
  void WebBrowser.openBrowserAsync(href);
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
