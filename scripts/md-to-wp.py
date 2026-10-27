"""
Markdown doc → WordPress block markup, ready to paste into a page's code editor
(or to save with wp-cli). Uses the same parser as docs-to-pdf.py, so the PDF and the
web page come from one source. The "# Title" line is skipped: the page has its own title.

    python -I scripts/md-to-wp.py docs/privacy/privacy-notice.md out.html
"""
import importlib.util
import re
import sys
from html import escape
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('docs_to_pdf', HERE / 'docs-to-pdf.py')
docs_to_pdf = importlib.util.module_from_spec(spec)
spec.loader.exec_module(docs_to_pdf)

# Bare addresses written in the doc that should be links on the website.
BARE_LINKS = {
    'ico.org.uk': 'https://ico.org.uk/',
    'tools.google.com/dlpage/gaoptout': 'https://tools.google.com/dlpage/gaoptout',
}


def inline(text):
    s = escape(text, quote=False)
    s = re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', s)
    s = re.sub(r'(?<![*\w])\*([^*\n]+)\*(?![*\w])', r'<em>\1</em>', s)
    s = re.sub(r'\b([\w.+-]+@[\w-]+(?:\.[\w-]+)+)\b', r'<a href="mailto:\1">\1</a>', s)
    for bare, href in BARE_LINKS.items():
        s = re.sub(r'(?<![/\w.])' + re.escape(bare) + r'(?![\w/])', '<a href="%s">%s</a>' % (href, bare), s)
    return s


def block(name, html, attrs=''):
    return '<!-- wp:%s%s -->\n%s\n<!-- /wp:%s -->' % (name, ' ' + attrs if attrs else '', html, name)


def to_blocks(blocks):
    out = []
    for kind, payload in blocks:
        if kind == 'h1':
            continue
        if kind == 'h2':
            out.append(block('heading', '<h2 class="wp-block-heading">%s</h2>' % inline(payload)))
        elif kind in ('h3', 'h4'):
            out.append(block('heading', '<h3 class="wp-block-heading">%s</h3>' % inline(payload), '{"level":3}'))
        elif kind == 'hr':
            out.append(block('separator', '<hr class="wp-block-separator has-alpha-channel-opacity"/>'))
        elif kind == 'p':
            out.append(block('paragraph', '<p>%s</p>' % inline(payload)))
        elif kind in ('ul', 'ol'):
            items = ''.join(block('list-item', '<li>%s</li>' % inline(t)) for t in payload)
            tag = 'ul' if kind == 'ul' else 'ol'
            out.append(block('list', '<%s class="wp-block-list">%s</%s>' % (tag, items, tag), '' if kind == 'ul' else '{"ordered":true}'))
        elif kind == 'table':
            head, *body = payload
            thead = '<thead><tr>%s</tr></thead>' % ''.join('<th>%s</th>' % inline(c) for c in head)
            tbody = '<tbody>%s</tbody>' % ''.join('<tr>%s</tr>' % ''.join('<td>%s</td>' % inline(c) for c in row) for row in body)
            out.append(block('table', '<figure class="wp-block-table"><table class="has-fixed-layout">%s%s</table></figure>' % (thead, tbody)))
    return '\n\n'.join(out) + '\n'


def main():
    src, dest = Path(sys.argv[1]), Path(sys.argv[2])
    dest.write_text(to_blocks(docs_to_pdf.parse(src.read_text(encoding='utf-8'))), encoding='utf-8', newline='\n')
    print('Wrote', dest)


if __name__ == '__main__':
    main()
