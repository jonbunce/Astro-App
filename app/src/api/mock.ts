// Sample data used until EXPO_PUBLIC_API_BASE is set. Adult fixtures and club news
// mirror the live website on 24 Oct 2026; children, money and messages are made up.

import { SITE_URL } from '@/theme';

import type { AppConfig, Child, HomeData, InboxItem, Me, NewsItem } from './types';

export const mockMe: Me = {
  id: 1,
  firstName: 'Sam',
  lastName: 'Sample',
  email: 'sam@example.com',
  roles: ['parent'],
  profileComplete: true,
};

export const mockFamily: Child[] = [
  {
    id: 101,
    firstName: 'Oliver',
    lastName: 'Sample',
    team: { id: 11, name: 'Panthers', ageGroup: 'U10', crest: 'panthers' },
    participation: 'signed',
    status: 'active',
    monthly: 22,
    paymentType: 'sibling_rate',
    breakdown: [
      { label: 'U10 Panthers subs', amount: 25 },
      { label: 'Sibling rate', amount: -3 },
    ],
    nextPayment: '2026-11-01',
    lastPayment: { date: '2026-10-01', amount: 22, status: 'paid' },
    arrears: null,
    consents: { photography: true, legal: true },
    medicalOnFile: true,
    emergencyOnFile: true,
    hasPhoto: false,
  },
  {
    id: 102,
    firstName: 'Ava',
    lastName: 'Sample',
    team: { id: 12, name: 'Sharks', ageGroup: 'U8', crest: 'sharks' },
    participation: 'signed',
    status: 'active',
    monthly: 22,
    paymentType: 'gocardless',
    breakdown: [{ label: 'U8 Sharks subs', amount: 22 }],
    nextPayment: '2026-11-01',
    lastPayment: { date: '2026-10-01', amount: 22, status: 'failed' },
    arrears: { total: 22, reason: "October's payment didn't go through" },
    consents: { photography: false, legal: true },
    medicalOnFile: true,
    emergencyOnFile: true,
    hasPhoto: false,
  },
];

const news: NewsItem[] = [
  {
    id: 3699,
    title: 'ASTRO is live: player registration goes digital',
    date: '2026-07-18',
    kind: 'club',
    category: 'Club news',
    image: 'astroLaunch',
    url: `${SITE_URL}/astro-is-live-player-registration-goes-digital-for-2026-27/`,
    featured: true,
  },
  {
    id: 9001,
    title: 'Panthers lift the cup at Codsall',
    date: '2026-10-23',
    kind: 'team',
    team: 'U10 Panthers',
    image: 'panthers',
    url: `${SITE_URL}/team-news/`,
  },
  {
    id: 3700,
    title: 'Welcome to 2026-27: your new-look website',
    date: '2026-08-25',
    kind: 'club',
    category: 'Club news',
    image: 'wallBadge',
    url: `${SITE_URL}/welcome-to-2026-27-your-new-look-wombourne-allstars-website/`,
  },
  {
    id: 3138,
    title: 'Building something bigger: Allstar Futures',
    date: '2025-04-14',
    kind: 'club',
    category: 'Future plans',
    image: 'futures',
    url: `${SITE_URL}/news/`,
  },
  {
    id: 9002,
    title: 'First festival of the season',
    date: '2026-10-21',
    kind: 'team',
    team: 'U8 Sharks',
    image: 'sharks',
    url: `${SITE_URL}/team-news/`,
  },
];

export function mockNews(filter: 'all' | 'mine' | 'club' | 'adults'): NewsItem[] {
  if (filter === 'mine') return news.filter((n) => n.team === 'U10 Panthers' || n.team === 'U8 Sharks');
  if (filter === 'club') return news.filter((n) => n.kind === 'club');
  if (filter === 'adults') return news.filter((n) => n.kind === 'adults');
  return news;
}

export const mockHome: HomeData = {
  fixtures: [
    {
      team: "Men's First Team",
      opponent: 'Avery',
      homeAway: 'A',
      kickoff: '2026-10-24T15:00:00',
      venue: 'Avery FC',
      league: 'Division One',
      position: '2nd',
      form: ['W', 'L', 'W', 'W', 'W'],
      photo: 'firstTeam',
    },
    {
      team: "Women's",
      opponent: 'Rubery Juniors Ravens',
      homeAway: 'H',
      kickoff: '2026-10-25T14:30:00',
      venue: 'Leisure Centre',
      position: '7th',
    },
    {
      team: 'Sunday',
      opponent: 'Little Vic United',
      homeAway: 'H',
      kickoff: '2026-10-25T10:30:00',
      venue: 'Brickbridge Lane',
      position: '3rd',
    },
  ],
  latest: [news[1]],
};

export const mockInbox: InboxItem[] = [
  {
    id: 1,
    type: 'club_message',
    title: 'Training cancelled tonight',
    body: 'The pitches are waterlogged, so all evening sessions are off. See you next week.',
    from: 'From the committee',
    createdAt: '2026-10-24T16:05:00',
    read: false,
  },
  {
    id: 2,
    type: 'team_news',
    title: 'New U10 Panthers team news',
    body: 'Panthers lift the cup at Codsall',
    image: 'panthers',
    createdAt: '2026-10-23T18:20:00',
    read: false,
  },
  {
    id: 3,
    type: 'payment',
    title: 'Payment collected',
    body: "Oliver's October subs were collected.",
    createdAt: '2026-10-01T09:00:00',
    read: true,
  },
  {
    id: 4,
    type: 'club_message',
    title: 'AGM 2026: the club in numbers',
    body: 'Thank you to everyone who came along.',
    from: 'From the committee',
    createdAt: '2026-10-19T20:00:00',
    read: true,
  },
  {
    id: 5,
    type: 'welcome',
    title: 'Welcome to the Allstars app',
    body: 'Club messages now arrive here and by email.',
    createdAt: '2026-10-17T12:00:00',
    read: true,
  },
];

export const mockConfig: AppConfig = {
  minVersion: '1.0.0',
  clubPages: [
    { title: 'Our story', url: `${SITE_URL}/our-story/`, image: 'pitch' },
    { title: 'Walking football', url: `${SITE_URL}/walking-football/`, image: 'walking' },
    { title: 'Meet the committee', url: `${SITE_URL}/meet-the-committee/` },
    { title: 'Our sponsors', url: `${SITE_URL}/sponsors-directory/` },
    { title: 'Girls & ladies', url: `${SITE_URL}/girls-ladies-football/` },
    { title: 'Coach or volunteer', url: `${SITE_URL}/coaching-opportunities/` },
  ],
  helpPages: [
    { title: 'Safeguarding', url: `${SITE_URL}/safeguarding/` },
    { title: 'Documents & policies', url: `${SITE_URL}/document-library/` },
    { title: 'Contact the club', url: `${SITE_URL}/contact-us/` },
    { title: 'Privacy policy', url: `${SITE_URL}/privacy-policy/` },
  ],
};
