// Shapes returned by the app API (wa/v1 and was/v1 in docs/build-plan.md).
// Image fields hold a URL from the API, or a bundled key in sample data (see src/images.ts).

export type Role = 'parent' | 'coach' | 'committee' | 'treasurer' | 'social_media' | 'registration_officer';

export interface Me {
  id: number;
  firstName: string;
  lastName: string;
  email: string;
  roles: Role[];
  profileComplete: boolean;
}

export interface Team {
  id: number;
  name: string;
  ageGroup: string;
  crest?: string | null;
}

export interface MoneyLine {
  label: string;
  amount: number;
}

export interface Payment {
  date: string;
  amount: number;
  status: 'paid' | 'pending' | 'failed';
}

export interface Child {
  id: number;
  firstName: string;
  lastName: string;
  team: Team;
  participation: 'signed' | 'train_only';
  status: 'active' | 'pending' | 'inactive' | 'withdrawn';
  monthly: number;
  /** team_direct = subs paid to the team, not the club; exempt = no subs. */
  paymentType: 'gocardless' | 'sibling_rate' | 'team_direct' | 'exempt';
  breakdown: MoneyLine[];
  nextPayment: string | null;
  lastPayment: Payment | null;
  arrears: { total: number; reason: string } | null;
  consents: { photography: boolean; legal: boolean };
  medicalOnFile: boolean;
  emergencyOnFile: boolean;
  /** Fetch with GET /was/v1/players/{id}/photo. */
  hasPhoto: boolean;
}

export interface Fixture {
  team: string;
  opponent: string;
  homeAway: 'H' | 'A';
  kickoff: string;
  venue?: string;
  league?: string;
  position?: string;
  form?: ('W' | 'D' | 'L')[];
  photo?: string | null;
}

export interface NewsItem {
  id: number;
  title: string;
  date: string;
  kind: 'club' | 'team' | 'adults';
  category?: string;
  team?: string;
  image?: string | null;
  url: string;
  featured?: boolean;
}

export interface InboxItem {
  id: number;
  type: 'club_message' | 'team_news' | 'payment' | 'welcome';
  title: string;
  body: string;
  from?: string;
  image?: string | null;
  /** Team news: the article on the website. */
  url?: string;
  createdAt: string;
  read: boolean;
}

export interface HomeData {
  fixtures: Fixture[];
  latest: NewsItem[];
}

export interface Session {
  id: number;
  device: string;
  platform: string;
  createdAt: string;
  lastUsedAt: string;
  current: boolean;
}

export interface InfoLink {
  title: string;
  url: string;
  image?: string | null;
  /** The page's slug on the website. */
  slug?: string;
  /** Shown as text in the app (GET /wa/v1/content/pages/{slug}); otherwise it opens as a web page. */
  native?: boolean;
}

/** A stretch of text with its marks; href is an http(s), mailto or tel link. */
export interface PageRun {
  t: string;
  b?: boolean;
  i?: boolean;
  href?: string;
}

/** One piece of a club page. The app skips block types it doesn't know. */
export type PageBlock =
  | { type: 'heading'; level: 2 | 3; text: string }
  /** The small label a designed page puts above a heading. */
  | { type: 'kicker'; text: string }
  | { type: 'paragraph'; content: PageRun[] }
  | { type: 'list'; ordered: boolean; items: PageRun[][] }
  /** rows → cells → runs; with header, the first row labels the columns. */
  | { type: 'table'; header: boolean; rows: PageRun[][][] }
  | { type: 'image'; src: string; alt: string; ratio?: number }
  | { type: 'button'; label: string; href: string }
  | { type: 'documents'; items: { title: string; description: string; url: string }[] }
  | { type: 'people'; items: { name: string; role: string; photo: string | null }[] }
  | { type: 'divider' }
  /** Where the page has a form: the app offers the website instead. */
  | { type: 'form' };

/** A club information page as text, straight from the website page. */
export interface PageContent {
  slug: string;
  title: string;
  url: string;
  modified: string;
  blocks: PageBlock[];
}

export interface AppConfig {
  /** Older app versions must update before they can be used. */
  minVersion: string;
  /** Store listings for the update prompt; null until the app is listed. */
  storeUrls?: { ios: string | null; android: string | null };
  clubPages: InfoLink[];
  helpPages: InfoLink[];
}
