import { Image } from 'expo-image';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { PageBlock, PageRun } from '@/api/types';
import { GlassButton } from '@/components/Buttons';
import { Glass } from '@/components/Glass';
import { Kicker } from '@/components/Kicker';
import { Heading, Txt } from '@/components/Txt';
import { openLink, openPage } from '@/lib/website';
import { colors, fonts } from '@/theme';

type Doc = Extract<PageBlock, { type: 'documents' }>['items'][number];
type Person = Extract<PageBlock, { type: 'people' }>['items'][number];

/** A club page's text from the website, set in the app's own type. */
export function PageBlocks({ blocks, pageUrl, title }: { blocks: PageBlock[]; pageUrl: string; title: string }) {
  return (
    <View style={styles.page}>
      {blocks.map((block, i) => (
        <Block key={i} block={block} pageUrl={pageUrl} title={title} />
      ))}
    </View>
  );
}

function Block({ block, pageUrl, title }: { block: PageBlock; pageUrl: string; title: string }) {
  switch (block.type) {
    case 'heading':
      return block.level === 2 ? (
        <Heading style={styles.h2}>{block.text}</Heading>
      ) : (
        <Txt accessibilityRole="header" style={styles.h3}>
          {block.text}
        </Txt>
      );
    case 'kicker':
      return (
        <Kicker small style={styles.kicker}>
          {block.text}
        </Kicker>
      );
    case 'paragraph':
      return (
        <Txt selectable style={styles.para}>
          <Runs runs={block.content} />
        </Txt>
      );
    case 'list':
      return (
        <View style={styles.list}>
          {block.items.map((item, i) => (
            <View key={i} style={styles.item}>
              {block.ordered ? <Txt style={styles.number}>{i + 1}.</Txt> : <View style={styles.bullet} />}
              <Txt selectable style={[styles.para, { flex: 1 }]}>
                <Runs runs={item} />
              </Txt>
            </View>
          ))}
        </View>
      );
    case 'table':
      return <Table header={block.header} rows={block.rows} />;
    case 'image':
      return (
        <Image
          source={{ uri: block.src }}
          accessibilityLabel={block.alt || undefined}
          accessible={!!block.alt}
          contentFit="cover"
          transition={200}
          style={[styles.image, { aspectRatio: block.ratio ?? 4 / 3 }]}
        />
      );
    case 'button':
      return <GlassButton label={block.label} onPress={() => openLink(block.href)} />;
    case 'documents':
      return <Documents items={block.items} />;
    case 'people':
      return <People items={block.items} />;
    case 'divider':
      return <View style={styles.divider} />;
    case 'form':
      return (
        <Glass tone="quiet" radius={20} style={styles.form}>
          <Txt variant="small">This part of the page is a form. You can fill it in on the website.</Txt>
          <GlassButton label="Open the form" onPress={() => openPage(pageUrl, title)} />
        </Glass>
      );
    default:
      return null; // a block type from a newer website version
  }
}

/** Text runs inside a paragraph. Inter's italic isn't bundled, so italic text shows upright. */
function Runs({ runs }: { runs: PageRun[] }) {
  return (
    <>
      {runs.map((run, i) =>
        run.href ? (
          <Text key={i} accessibilityRole="link" onPress={() => openLink(run.href!)} style={styles.link}>
            {run.t}
          </Text>
        ) : (
          detectLinks(run.t).map((part, j) =>
            part.href ? (
              <Text key={`${i}.${j}`} accessibilityRole="link" onPress={() => openLink(part.href!)} style={styles.link}>
                {part.t}
              </Text>
            ) : (
              <Text key={`${i}.${j}`} style={run.b ? styles.bold : undefined}>
                {part.t}
              </Text>
            ),
          )
        ),
      )}
    </>
  );
}

// Email addresses and UK phone numbers written as plain text ("Mind: 0300 123 3393") become tappable.
const DETECT = /([\w.+-]+@[\w-]+(?:\.[\w-]+)+)|(\b0\d{3,4} ?\d{3} ?\d{3,4}\b|\b116 ?\d{3}\b)/g;

function detectLinks(text: string): PageRun[] {
  const parts: PageRun[] = [];
  let last = 0;
  for (const m of text.matchAll(DETECT)) {
    const at = m.index ?? 0;
    if (at > last) parts.push({ t: text.slice(last, at) });
    parts.push({ t: m[0], href: m[1] ? `mailto:${m[1]}` : `tel:${m[0].replace(/ /g, '')}` });
    last = at + m[0].length;
  }
  if (last < text.length) parts.push({ t: text.slice(last) });
  return parts;
}

const plain = (runs: PageRun[] | undefined) => (runs ?? []).map((r) => r.t).join('');

/** Tables don't fit a phone screen, so each row becomes a card, labelled by the header row. */
function Table({ header, rows }: { header: boolean; rows: PageRun[][][] }) {
  const head = header ? rows[0] : null;
  const body = header ? rows.slice(1) : rows;
  return (
    <View style={{ gap: 8 }}>
      {body.map((row, r) => (
        <Glass key={r} tone="quiet" radius={18} style={styles.row}>
          {row.map((cell, c) =>
            cell.length ? (
              <View key={c} style={{ gap: 3 }}>
                {head && plain(head[c]) ? <Txt variant="label">{plain(head[c])}</Txt> : null}
                <Txt selectable style={[styles.cell, !head && c === 0 && styles.bold]}>
                  <Runs runs={cell} />
                </Txt>
              </View>
            ) : null,
          )}
        </Glass>
      ))}
    </View>
  );
}

function Documents({ items }: { items: Doc[] }) {
  return (
    <Glass radius={22} style={{ paddingHorizontal: 16 }}>
      {items.map((doc, i) => (
        <Pressable
          key={`${doc.url}-${i}`}
          accessibilityRole="link"
          accessibilityHint="Opens the document"
          onPress={() => openLink(doc.url)}
          style={({ pressed }) => [styles.doc, i < items.length - 1 && styles.docLine, pressed && { opacity: 0.7 }]}>
          <View style={styles.docType}>
            <Text style={styles.docTypeText}>{(/\.(\w+)(?:[?#]|$)/.exec(doc.url)?.[1] ?? 'doc').toUpperCase()}</Text>
          </View>
          <View style={{ flex: 1, gap: 2 }}>
            <Txt style={{ fontFamily: fonts.semibold }}>{doc.title}</Txt>
            {doc.description ? (
              <Txt variant="small" numberOfLines={2}>
                {doc.description}
              </Txt>
            ) : null}
          </View>
          <Txt style={styles.chevron}>›</Txt>
        </Pressable>
      ))}
    </Glass>
  );
}

function People({ items }: { items: Person[] }) {
  return (
    <View style={styles.people}>
      {items.map((person, i) => (
        <Glass key={`${person.name}-${i}`} tone="quiet" radius={20} style={styles.person}>
          {person.photo ? (
            <Image source={{ uri: person.photo }} contentFit="cover" transition={200} style={styles.face} accessible={false} />
          ) : (
            <View style={[styles.face, styles.faceEmpty]} />
          )}
          <Txt style={styles.name} numberOfLines={2}>
            {person.name}
          </Txt>
          {person.role ? (
            <Txt variant="small" style={{ textAlign: 'center' }} numberOfLines={2}>
              {person.role}
            </Txt>
          ) : null}
        </Glass>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  page: { gap: 14 },
  h2: { fontSize: 24, lineHeight: 26, marginTop: 12 },
  kicker: { marginTop: 14, marginBottom: -6 },
  h3: { fontFamily: fonts.bold, fontSize: 17, lineHeight: 23, marginTop: 4 },
  para: { fontSize: 16, lineHeight: 24, color: 'rgba(255,255,255,0.9)' },
  bold: { fontFamily: fonts.bold, color: colors.white },
  link: { fontFamily: fonts.semibold, color: colors.gold },
  list: { gap: 8 },
  item: { flexDirection: 'row', gap: 12 },
  bullet: { width: 6, height: 6, borderRadius: 3, backgroundColor: colors.red, marginTop: 9 },
  number: { fontFamily: fonts.bold, color: colors.gold, fontSize: 16, lineHeight: 24, minWidth: 18 },
  image: { width: '100%', borderRadius: 20, backgroundColor: colors.carbon700 },
  divider: { height: 1, backgroundColor: colors.line, marginVertical: 6 },
  form: { padding: 16, gap: 12 },
  row: { padding: 14, gap: 10 },
  cell: { fontSize: 15, lineHeight: 22, color: 'rgba(255,255,255,0.9)' },
  doc: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 14 },
  docLine: { borderBottomWidth: 1, borderBottomColor: colors.line },
  docType: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(200,16,46,0.22)',
    borderWidth: 1,
    borderColor: 'rgba(255,90,110,0.36)',
  },
  docTypeText: { color: colors.white, fontFamily: fonts.heavy, fontSize: 10, letterSpacing: 0.8 },
  chevron: { color: colors.textFaint, fontSize: 20 },
  people: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  person: { width: '48.8%', alignItems: 'center', paddingVertical: 16, paddingHorizontal: 10, gap: 6 },
  face: { width: 76, height: 76, borderRadius: 38, marginBottom: 4 },
  faceEmpty: { backgroundColor: colors.carbon700 },
  name: { fontFamily: fonts.bold, textAlign: 'center' },
});
