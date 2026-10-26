"""
Turn one of the Markdown documents in docs/ into a club-branded A4 PDF.

    python scripts/docs-to-pdf.py docs/privacy/dpia.md "out/DPIA.pdf" --status "Draft for committee review"

Handles the Markdown these documents use: # / ## / ### headings, paragraphs, - and 1. lists,
tables, ---, **bold**, *italic*, `code` and plain links. Needs reportlab (pip install reportlab).
"""

import argparse
import re
from datetime import date
from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas as rl_canvas
from reportlab.platypus import (
    BaseDocTemplate, Flowable, Frame, KeepTogether, ListFlowable, ListItem, NextPageTemplate,
    PageTemplate, Paragraph, Spacer, Table, TableStyle,
)

ROOT = Path(__file__).resolve().parent.parent
APP = ROOT / 'app'
INTER = APP / 'node_modules' / '@expo-google-fonts' / 'inter'
BADGE = APP / 'assets' / 'images' / 'brand' / 'badge.png'

CARBON = colors.HexColor('#0b0c0f')
INK = colors.HexColor('#14161a')
RED = colors.HexColor('#C8102E')
GOLD = colors.HexColor('#E39A00')
MUTED = colors.HexColor('#5b616b')
LINE = colors.HexColor('#dfe2e7')
PAPER = colors.HexColor('#f6f7f9')
RISK = {'low': colors.HexColor('#1f8a54'), 'medium': colors.HexColor('#b06f00'), 'must': RED}

PAGE_W, PAGE_H = A4
MARGIN_X = 18 * mm
BAND_H = 44 * mm


def register_fonts():
    fonts = {
        'Inter': '400Regular/Inter_400Regular.ttf',
        'Inter-Italic': '400Regular_Italic/Inter_400Regular_Italic.ttf',
        'Inter-SemiBold': '600SemiBold/Inter_600SemiBold.ttf',
        'Inter-Bold': '700Bold/Inter_700Bold.ttf',
        'Inter-BoldItalic': '700Bold_Italic/Inter_700Bold_Italic.ttf',
    }
    for name, rel in fonts.items():
        pdfmetrics.registerFont(TTFont(name, str(INTER / rel)))
    pdfmetrics.registerFontFamily('Inter', normal='Inter', bold='Inter-Bold', italic='Inter-Italic', boldItalic='Inter-BoldItalic')
    pdfmetrics.registerFont(TTFont('Display', str(APP / 'assets' / 'fonts' / 'ArchivoCondensedBlackItalic.ttf')))
    pdfmetrics.registerFont(TTFont('DisplaySemi', str(APP / 'assets' / 'fonts' / 'ArchivoSemiCondensedExtraBoldItalic.ttf')))


def styles():
    body = ParagraphStyle('body', fontName='Inter', fontSize=9.6, leading=14.2, textColor=INK, spaceAfter=6, alignment=TA_LEFT)
    return {
        'body': body,
        'h2': ParagraphStyle('h2', parent=body, fontName='DisplaySemi', fontSize=15, leading=18, spaceBefore=14, spaceAfter=7, textColor=INK),
        'h3': ParagraphStyle('h3', parent=body, fontName='Inter-Bold', fontSize=10.2, leading=14, spaceBefore=8, spaceAfter=3),
        'bullet': ParagraphStyle('bullet', parent=body, spaceAfter=2.5),
        'cell': ParagraphStyle('cell', parent=body, fontSize=8.4, leading=11.4, spaceAfter=0),
        'head': ParagraphStyle('head', parent=body, fontName='Inter-SemiBold', fontSize=8.2, leading=11, spaceAfter=0, textColor=colors.white),
    }


# ── Inline Markdown ───────────────────────────────────────────────────────────

def inline(text, cell_colour_risk=False):
    """Markdown inline marks to reportlab's paragraph markup."""
    parts = re.split(r'(`[^`]+`)', text)
    out = []
    for part in parts:
        if part.startswith('`') and part.endswith('`') and len(part) > 1:
            out.append('<font name="Inter-SemiBold" color="#8c0a20">' + escape(part[1:-1]) + '</font>')
            continue
        s = escape(part)
        s = re.sub(r'(https?://[^\s<)]+[^\s<).,;:])', r'<link href="\1" color="#C8102E">\1</link>', s)
        if cell_colour_risk:
            s = re.sub(r'\*\*([^*]+)\*\*', lambda m: '<font color="%s"><b>%s</b></font>' % (risk_colour(m.group(1)), m.group(1)), s)
        s = re.sub(r'\*\*([^*]+)\*\*', r'<b>\1</b>', s)
        s = re.sub(r'(?<![*\w])\*([^*\n]+)\*(?![*\w])', r'<i>\1</i>', s)
        out.append(s)
    return ''.join(out)


def risk_colour(label):
    l = label.lower()
    if l.startswith('must'):
        return RISK['must'].hexval().replace('0x', '#')
    if 'medium' in l:
        return RISK['medium'].hexval().replace('0x', '#')
    if 'low' in l:
        return RISK['low'].hexval().replace('0x', '#')
    return '#14161a'


# ── Block parsing ─────────────────────────────────────────────────────────────

def parse(md):
    """Markdown → list of (kind, payload) blocks."""
    lines = md.replace('\r', '').split('\n')
    blocks, para, i = [], [], 0

    def flush():
        if para:
            blocks.append(('p', ' '.join(l.strip() for l in para)))
            para.clear()

    while i < len(lines):
        line = lines[i]
        stripped = line.strip()
        if not stripped:
            flush(); i += 1; continue
        if stripped.startswith('#'):
            flush()
            level = len(stripped) - len(stripped.lstrip('#'))
            blocks.append(('h%d' % level, stripped[level:].strip()))
            i += 1; continue
        if stripped == '---':
            flush(); blocks.append(('hr', None)); i += 1; continue
        if stripped.startswith('|'):
            flush()
            rows = []
            while i < len(lines) and lines[i].strip().startswith('|'):
                row = lines[i].strip().strip('|')
                cells = [c.strip() for c in re.split(r'(?<!\\)\|', row)]
                if not all(re.fullmatch(r':?-{2,}:?', c) for c in cells if c):
                    rows.append([c.replace('\\|', '|') for c in cells])
                i += 1
            blocks.append(('table', rows))
            continue
        m = re.match(r'^(\s*)(-|\d+\.)\s+(.*)$', line)
        if m:
            flush()
            ordered = m.group(2) != '-'
            items = []
            while i < len(lines):
                m = re.match(r'^(\s*)(-|\d+\.)\s+(.*)$', lines[i])
                if m and (m.group(2) != '-') == ordered:
                    items.append(m.group(3).strip()); i += 1
                    # continuation lines are indented
                    while i < len(lines) and lines[i].startswith('  ') and not re.match(r'^\s*(-|\d+\.)\s', lines[i]) and lines[i].strip():
                        items[-1] += ' ' + lines[i].strip(); i += 1
                else:
                    break
            blocks.append(('ol' if ordered else 'ul', items))
            continue
        if para and re.match(r'^\*\*[^*]+:\*\*', stripped):
            flush()  # "**Label:** text" lines are separate lines, not one run-on paragraph
        para.append(line); i += 1
    flush()
    return blocks


class Rule(Flowable):
    def __init__(self, width, colour=LINE, thickness=0.8, space=8):
        super().__init__()
        self.width, self.colour, self.thickness, self.space = width, colour, thickness, space

    def wrap(self, *_):
        return self.width, self.space * 2

    def draw(self):
        self.canv.setStrokeColor(self.colour)
        self.canv.setLineWidth(self.thickness)
        self.canv.line(0, self.space, self.width, self.space)


class H2(Flowable):
    """Section heading: red edge, italic condensed capitals."""
    def __init__(self, text, style, width):
        super().__init__()
        self.p = Paragraph(escape(text).upper(), style)
        self.style, self.width = style, width

    def wrap(self, aw, ah):
        _, h = self.p.wrap(aw - 10, ah)
        self.h = h
        return aw, h + self.style.spaceBefore + 4

    def draw(self):
        y = 4
        self.canv.setFillColor(RED)
        self.canv.rect(0, y + 1, 3.2, self.h - 2, stroke=0, fill=1)
        self.p.drawOn(self.canv, 10, y)


def col_widths(rows, total):
    """Share the width by how much text each column holds, within sensible limits."""
    n = len(rows[0])
    weight = []
    for c in range(n):
        lengths = [len(r[c]) if c < len(r) else 0 for r in rows]
        weight.append(max(4, min(60, sum(lengths) / len(lengths) + 0.3 * max(lengths))))
    s = sum(weight)
    widths = [total * w / s for w in weight]
    # very short columns (like "#") get just enough room
    for c in range(n):
        if max(len(r[c]) for r in rows if c < len(r)) <= 3:
            widths[c] = 9 * mm
    rest = total - sum(w for c, w in enumerate(widths) if max(len(r[c]) for r in rows if c < len(r)) <= 3)
    flex = [c for c in range(n) if max(len(r[c]) for r in rows if c < len(r)) > 3]
    fs = sum(weight[c] for c in flex)
    for c in flex:
        widths[c] = rest * weight[c] / fs
    return widths


def build_table(rows, st, width):
    header, body = rows[0], rows[1:]
    risk_col = next((i for i, h in enumerate(header) if h.lower().startswith('residual')), None)
    data = [[Paragraph(inline(h), st['head']) for h in header]]
    for r in body:
        cells = []
        for c in range(len(header)):
            text = r[c] if c < len(r) else ''
            cells.append(Paragraph(inline(text, cell_colour_risk=(c == risk_col)), st['cell']))
        data.append(cells)
    # A sign-off table ("Name / date" column) needs room to write in.
    signoff = len(header) > 1 and header[1].lower().startswith('name')
    widths = [width * 0.4] + [width * 0.6 / (len(header) - 1)] * (len(header) - 1) if signoff else col_widths(rows, width)
    t = Table(data, colWidths=widths, repeatRows=1,
              rowHeights=[None] + ([34] * len(body) if signoff else [None] * len(body)))
    style = [
        ('BACKGROUND', (0, 0), (-1, 0), CARBON),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('LEFTPADDING', (0, 0), (-1, -1), 6),
        ('RIGHTPADDING', (0, 0), (-1, -1), 6),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('LINEBELOW', (0, 1), (-1, -1), 0.5, LINE),
        ('BOX', (0, 0), (-1, -1), 0.5, LINE),
    ]
    for i in range(1, len(data)):
        if i % 2 == 0:
            style.append(('BACKGROUND', (0, i), (-1, i), PAPER))
    t.setStyle(TableStyle(style))
    return t


def story_from(blocks, st, width):
    story = []
    for kind, payload in blocks:
        if kind == 'h1':
            continue  # the title goes in the page header
        if kind == 'h2':
            story.append(H2(payload, st['h2'], width))
        elif kind in ('h3', 'h4'):
            story.append(Paragraph(inline(payload), st['h3']))
        elif kind == 'hr':
            story.append(Rule(width))
        elif kind == 'p':
            if re.fullmatch(r'\*\*[^*]+\*\*', payload):
                story.append(Paragraph(inline(payload[2:-2]), st['h3']))
            else:
                story.append(Paragraph(inline(payload), st['body']))
        elif kind in ('ul', 'ol'):
            items = [ListItem(Paragraph(inline(t), st['bullet']), leftIndent=14) for t in payload]
            if kind == 'ul':
                lf = ListFlowable(items, bulletType='bullet', start='•', bulletColor=RED, bulletFontSize=9, leftIndent=14)
            else:
                lf = ListFlowable(items, bulletType='1', bulletFontName='Inter-Bold', bulletFontSize=9, bulletColor=RED, leftIndent=16)
            story.append(lf)
            story.append(Spacer(1, 4))
        elif kind == 'table':
            story.append(Spacer(1, 2))
            story.append(build_table(payload, st, width))
            story.append(Spacer(1, 9))
    return story


# ── Pages ─────────────────────────────────────────────────────────────────────

class NumberedCanvas(rl_canvas.Canvas):
    """Adds "Page x of y" once the total is known."""
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._pages = []

    def showPage(self):
        self._pages.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        total = len(self._pages)
        for state in self._pages:
            self.__dict__.update(state)
            self.setFont('Inter', 7.6)
            self.setFillColor(MUTED)
            self.drawRightString(PAGE_W - MARGIN_X, 11 * mm, 'Page %d of %d' % (self._pageNumber, total))
            super().showPage()
        super().save()


def make_doc(out, title, kicker, status, footer):
    doc = BaseDocTemplate(str(out), pagesize=A4, title=title, author='Wombourne Allstars FC',
                          leftMargin=MARGIN_X, rightMargin=MARGIN_X, topMargin=20 * mm, bottomMargin=20 * mm)
    width = PAGE_W - 2 * MARGIN_X

    def footer_text(c):
        c.setStrokeColor(LINE)
        c.setLineWidth(0.6)
        c.line(MARGIN_X, 15 * mm, PAGE_W - MARGIN_X, 15 * mm)
        c.setFont('Inter', 7.6)
        c.setFillColor(MUTED)
        c.drawString(MARGIN_X, 11 * mm, footer)

    def first(c, _doc):
        c.saveState()
        c.setFillColor(CARBON)
        c.rect(0, PAGE_H - BAND_H, PAGE_W, BAND_H, stroke=0, fill=1)
        c.setFillColor(RED)
        c.rect(0, PAGE_H - BAND_H - 2.4, PAGE_W, 2.4, stroke=0, fill=1)
        c.setFillColor(GOLD)
        c.rect(0, PAGE_H - BAND_H - 3.4, PAGE_W * 0.22, 1.0, stroke=0, fill=1)
        badge = 27 * mm
        c.drawImage(str(BADGE), MARGIN_X, PAGE_H - BAND_H + (BAND_H - badge) / 2, badge, badge, mask='auto')
        x = MARGIN_X + badge + 7 * mm
        # kicker: slanted red tag
        c.setFont('Inter-Bold', 7.4)
        tag_w = c.stringWidth(kicker.upper(), 'Inter-Bold', 7.4) + 10
        ty = PAGE_H - 15.5 * mm
        p = c.beginPath()
        p.moveTo(x + 2, ty - 3); p.lineTo(x + tag_w + 2, ty - 3); p.lineTo(x + tag_w, ty + 9.5); p.lineTo(x, ty + 9.5); p.close()
        c.setFillColor(RED)
        c.drawPath(p, stroke=0, fill=1)
        c.setFillColor(colors.white)
        c.drawString(x + 5, ty, kicker.upper())
        # title, shrunk to fit
        size = 25
        while size > 14 and c.stringWidth(title.upper(), 'Display', size) > PAGE_W - x - MARGIN_X:
            size -= 1
        c.setFont('Display', size)
        c.drawString(x, PAGE_H - 27.5 * mm, title.upper())
        c.setFont('Inter', 8.6)
        c.setFillColor(colors.HexColor('#c9ccd2'))
        c.drawString(x, PAGE_H - 34.5 * mm, status)
        footer_text(c)
        c.restoreState()

    def later(c, _doc):
        c.saveState()
        c.setFont('Inter-SemiBold', 7.6)
        c.setFillColor(MUTED)
        c.drawString(MARGIN_X, PAGE_H - 12 * mm, 'WOMBOURNE ALLSTARS FC  ·  ' + title.upper())
        c.setFillColor(RED)
        c.rect(MARGIN_X, PAGE_H - 14.2 * mm, 18 * mm, 1.2, stroke=0, fill=1)
        footer_text(c)
        c.restoreState()

    top_first = BAND_H + 10 * mm
    f1 = Frame(MARGIN_X, 20 * mm, width, PAGE_H - top_first - 20 * mm, id='first', leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
    f2 = Frame(MARGIN_X, 20 * mm, width, PAGE_H - 40 * mm, id='later', leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
    doc.addPageTemplates([PageTemplate('first', [f1], onPage=first), PageTemplate('later', [f2], onPage=later)])
    return doc, width


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('source')
    ap.add_argument('out')
    ap.add_argument('--title', help='defaults to the document\'s first # heading')
    ap.add_argument('--kicker', default='Allstars app')
    ap.add_argument('--status', default='Draft for committee review')
    args = ap.parse_args()

    register_fonts()
    md = Path(args.source).read_text(encoding='utf-8')
    blocks = parse(md)
    title = args.title or next((p for k, p in blocks if k == 'h1'), Path(args.source).stem)
    today = date.today().strftime('%d %B %Y').lstrip('0')
    status = '%s  ·  %s' % (args.status, today)
    footer = 'Wombourne Allstars FC  ·  %s  ·  %s' % (title, args.status)

    out = Path(args.out)
    out.parent.mkdir(parents=True, exist_ok=True)
    doc, width = make_doc(out, title, args.kicker, status, footer)
    st = styles()
    story = [NextPageTemplate('later')] + story_from(blocks, st, width)
    doc.build(story, canvasmaker=NumberedCanvas)
    print('Wrote', out)


if __name__ == '__main__':
    main()
