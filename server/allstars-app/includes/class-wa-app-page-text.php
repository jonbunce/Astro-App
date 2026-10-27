<?php
/**
 * A club page's rendered HTML → plain blocks for the app's text pages (GET /content/pages/{slug}).
 *
 * The page is walked once. Headings (and the small labels above them), paragraphs, lists,
 * tables, images, dividers and stand-alone links come through; theme layout wrappers (Flatsome rows, columns, sections)
 * are flattened away, and scripts, styles and form fields are dropped. A form leaves a
 * "form" block so the app can offer the website instead. Two layouts get blocks of their own:
 * a table of document links (Document Library) becomes "documents", and Flatsome boxes with a
 * person-name (Meet the Committee) become "people".
 *
 * Block shapes: docs/api-contract.md (PageBlock). Text runs are { t, b?, i?, href? }.
 */

defined( 'ABSPATH' ) || exit;

class WA_App_Page_Text {

    /** Elements whose content never reaches the app. */
    const SKIP = array(
        'script', 'style', 'noscript', 'svg', 'template', 'iframe', 'object', 'video', 'audio', 'canvas',
        'button', 'input', 'select', 'textarea', 'label', 'option', 'head', 'meta', 'link',
    );

    /** Elements that sit inside a line of text rather than starting a new block. */
    const INLINE = array(
        'a', 'strong', 'b', 'em', 'i', 'span', 'br', 'small', 'u', 'abbr', 'code', 'sup', 'sub', 'mark',
        'time', 'cite', 'q', 's', 'font',
    );

    /** Links to files rather than pages (a table of these is a document list). */
    const FILE_LINK = '/\.(pdf|docx?|xlsx?|pptx?|odt|rtf)(\?|#|$)/i';

    /** A bold-only paragraph up to this long reads as a heading (older pages bold their headings). */
    const BOLD_HEADING_MAX = 90;

    const MAX_BLOCKS = 400;

    /** @var string Site URL that relative links are resolved against. */
    private $base;

    /** @var DOMXPath */
    private $xpath;

    /** @var array[] */
    private $blocks = array();

    /** @var array[] Loose text runs waiting to become a paragraph. */
    private $pending = array();

    private function __construct( string $base, DOMXPath $xpath ) {
        $this->base  = rtrim( $base, '/' ) . '/';
        $this->xpath = $xpath;
    }

    /**
     * @param string $html  The page's rendered content (after the_content).
     * @param string $base  Site URL that relative links are resolved against.
     * @param string $title The page title: a heading repeating it near the top is dropped, since the app shows the title.
     * @return array[] PageBlock list.
     */
    public static function blocks( string $html, string $base, string $title = '' ): array {
        $doc  = new DOMDocument();
        $prev = libxml_use_internal_errors( true );
        // The encoding declaration makes libxml read the HTML as UTF-8 instead of Latin-1.
        $doc->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        $body = $doc->getElementsByTagName( 'body' )->item( 0 );
        $self = new self( $base, new DOMXPath( $doc ) );
        if ( $body ) {
            $self->walk( $body );
            $self->flush();
        }
        return $self->without_title( $title );
    }

    // ── Walking the page ─────────────────────────────────────────────────────

    private function walk( DOMNode $node ) {
        foreach ( $node->childNodes as $child ) {
            if ( count( $this->blocks ) >= self::MAX_BLOCKS ) {
                return;
            }
            if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
                $this->pending[] = self::run( $child->nodeValue, false, false, null );
                continue;
            }
            if ( ! $child instanceof DOMElement ) {
                continue;
            }
            $tag = strtolower( $child->tagName );
            if ( in_array( $tag, self::SKIP, true ) || self::hidden( $child ) ) {
                continue;
            }

            if ( 'form' === $tag ) {
                $this->flush();
                $this->add( array( 'type' => 'form' ) );
            } elseif ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
                $this->flush();
                $text = self::one_line( $this->text_of( $child ) );
                if ( '' !== $text ) {
                    $this->add( array( 'type' => 'heading', 'level' => (int) $m[1] <= 2 ? 2 : 3, 'text' => $text ) );
                }
            } elseif ( 'p' === $tag && preg_match( '/(^|\s)[\w-]*(kicker|eyebrow)[\w-]*(\s|$)/i', $child->getAttribute( 'class' ) ) ) {
                // The small label a designed page puts above a heading ("Our volunteers").
                $this->flush();
                $text = self::one_line( $this->text_of( $child ) );
                if ( '' !== $text ) {
                    $this->add( array( 'type' => 'kicker', 'text' => $text ) );
                }
            } elseif ( 'p' === $tag || 'blockquote' === $tag ) {
                $this->flush();
                $runs = $this->tidy( $this->runs( $child ) );
                if ( $runs ) {
                    $this->paragraph( $runs );
                }
                $img = $this->first( $child, 'img' );
                if ( $img ) {
                    $this->image( $img );
                }
            } elseif ( 'ul' === $tag || 'ol' === $tag ) {
                $this->flush();
                $this->a_list( $child );
            } elseif ( 'table' === $tag ) {
                $this->flush();
                $this->table( $child );
            } elseif ( 'hr' === $tag ) {
                $this->flush();
                $this->add( array( 'type' => 'divider' ) );
            } elseif ( 'img' === $tag ) {
                $this->flush();
                $this->image( $child );
            } elseif ( self::has_class( $child, 'box' ) && $this->find_class( $child, 'person-name' ) ) {
                $this->flush();
                $this->person( $child );
            } elseif ( in_array( $tag, self::INLINE, true ) ) {
                $this->element_runs( $child, false, false, null, $this->pending );
            } else {
                // Layout wrapper: its contents are blocks of their own.
                $this->flush();
                $this->walk( $child );
                $this->flush();
            }
        }
    }

    private function add( array $block ) {
        $last = $this->blocks ? $this->blocks[ count( $this->blocks ) - 1 ] : null;
        if ( $last && 'people' === $block['type'] && 'people' === $last['type'] ) {
            $this->blocks[ count( $this->blocks ) - 1 ]['items'] = array_merge( $last['items'], $block['items'] );
        } elseif ( $last && 'form' === $block['type'] && 'form' === $last['type'] ) {
            return;
        } else {
            $this->blocks[] = $block;
        }
    }

    /** Loose text gathered between blocks becomes a paragraph of its own. */
    private function flush() {
        $runs          = $this->tidy( $this->pending );
        $this->pending = array();
        if ( $runs ) {
            $this->paragraph( $runs );
        }
    }

    /** A paragraph, or a button if it is nothing but one link, or a heading if it is a short bold line. */
    private function paragraph( array $runs ) {
        $text  = self::one_line( implode( '', array_column( $runs, 't' ) ) );
        $hrefs = array_values( array_unique( array_map( function ( $r ) {
            return $r['href'] ?? '';
        }, $runs ) ) );

        if ( 1 === count( $hrefs ) && '' !== $hrefs[0] ) {
            $this->add( array( 'type' => 'button', 'label' => $text, 'href' => $hrefs[0] ) );
            return;
        }
        $all_bold = true;
        foreach ( $runs as $r ) {
            if ( empty( $r['b'] ) && '' !== trim( $r['t'] ) ) {
                $all_bold = false;
                break;
            }
        }
        $no_links = array( '' ) === $hrefs;
        if ( $all_bold && $no_links && mb_strlen( $text ) <= self::BOLD_HEADING_MAX && ':' !== substr( $text, -1 ) ) {
            $this->add( array( 'type' => 'heading', 'level' => 3, 'text' => $text ) );
            return;
        }
        $this->add( array( 'type' => 'paragraph', 'content' => $runs ) );
    }

    private function a_list( DOMElement $list ) {
        $items = array();
        $this->list_items( $list, $items );
        if ( $items ) {
            $this->add( array( 'type' => 'list', 'ordered' => 'ol' === strtolower( $list->tagName ), 'items' => $items ) );
        }
    }

    /** Items of a list; nested lists follow their parent item at the same level. */
    private function list_items( DOMElement $list, array &$items ) {
        foreach ( $list->childNodes as $li ) {
            if ( ! $li instanceof DOMElement || 'li' !== strtolower( $li->tagName ) || self::hidden( $li ) ) {
                continue;
            }
            $runs = $this->tidy( $this->runs( $li ) );
            if ( $runs ) {
                $items[] = $runs;
            }
            foreach ( $li->childNodes as $sub ) {
                if ( $sub instanceof DOMElement && in_array( strtolower( $sub->tagName ), array( 'ul', 'ol' ), true ) ) {
                    $this->list_items( $sub, $items );
                }
            }
        }
    }

    private function table( DOMElement $table ) {
        $rows   = array();
        $header = false;
        foreach ( $this->xpath->query( './/tr', $table ) as $tr ) {
            $cells   = array();
            $all_th  = true;
            foreach ( $tr->childNodes as $cell ) {
                if ( $cell instanceof DOMElement && in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
                    $cells[] = $this->tidy( $this->runs( $cell ) );
                    $all_th  = $all_th && 'th' === strtolower( $cell->tagName );
                }
            }
            if ( ! array_filter( $cells ) ) {
                continue;
            }
            if ( ! $rows ) {
                $in_head = $tr->parentNode instanceof DOMElement && 'thead' === strtolower( $tr->parentNode->tagName );
                $header  = $in_head || $all_th;
            }
            $rows[] = $cells;
        }
        if ( ! $rows ) {
            return;
        }

        // Every row ending in a link to a file: a list of documents.
        $body  = $header ? array_slice( $rows, 1 ) : $rows;
        $items = array();
        foreach ( $body as $row ) {
            $link = null;
            foreach ( end( $row ) ?: array() as $r ) {
                if ( ! empty( $r['href'] ) ) {
                    $link = $r['href'];
                    break;
                }
            }
            if ( ! $link || ! preg_match( self::FILE_LINK, $link ) ) {
                $items = array();
                break;
            }
            $title   = count( $row ) > 1 ? self::one_line( implode( '', array_column( $row[0], 't' ) ) ) : '';
            $items[] = array(
                'title'       => '' !== $title ? $title : 'Document',
                'description' => count( $row ) > 2 ? self::one_line( implode( '', array_column( $row[1], 't' ) ) ) : '',
                'url'         => $link,
            );
        }
        if ( $items ) {
            $this->add( array( 'type' => 'documents', 'items' => $items ) );
            return;
        }
        $this->add( array( 'type' => 'table', 'header' => $header, 'rows' => $rows ) );
    }

    private function image( DOMElement $img ) {
        $src    = $this->image_src( $img );
        $width  = (int) $img->getAttribute( 'width' );
        $height = (int) $img->getAttribute( 'height' );
        if ( ! $src || ( $width && $width < 48 ) || ( $height && $height < 48 ) ) {
            return; // icons, spacers and tracking pixels
        }
        $block = array(
            'type' => 'image',
            'src'  => $src,
            'alt'  => trim( self::norm( $img->getAttribute( 'alt' ) ) ),
        );
        if ( $width && $height ) {
            $block['ratio'] = round( $width / $height, 4 );
        }
        $this->add( $block );
    }

    /** A Flatsome person box: photo, name and role. */
    private function person( DOMElement $box ) {
        $name = $this->find_class( $box, 'person-name' );
        $role = $this->find_class( $box, 'person-title' );
        $img  = $this->first( $box, 'img' );
        $this->add( array(
            'type'  => 'people',
            'items' => array( array(
                'name'  => $name ? self::one_line( $this->text_of( $name ) ) : '',
                'role'  => $role ? self::one_line( $this->text_of( $role ) ) : '',
                'photo' => $img ? $this->image_src( $img ) : null,
            ) ),
        ) );
    }

    private function without_title( string $title ): array {
        $title = strtolower( self::one_line( $title ) );
        foreach ( array_slice( $this->blocks, 0, 3, true ) as $i => $block ) {
            if ( 'heading' === $block['type'] && '' !== $title && strtolower( $block['text'] ) === $title ) {
                // Its label goes with it.
                $from = ( $i > 0 && 'kicker' === $this->blocks[ $i - 1 ]['type'] ) ? $i - 1 : $i;
                array_splice( $this->blocks, $from, $i - $from + 1 );
                break;
            }
        }
        return $this->blocks;
    }

    // ── Text runs ────────────────────────────────────────────────────────────

    private static function run( string $text, bool $bold, bool $italic, ?string $href ): array {
        return array( 't' => self::norm( $text ), 'b' => $bold, 'i' => $italic, 'href' => $href );
    }

    /** Runs for everything inside $node. Lists and tables inside it are left to their own blocks. */
    private function runs( DOMNode $node, bool $bold = false, bool $italic = false, ?string $href = null, array &$out = array() ): array {
        foreach ( $node->childNodes as $child ) {
            if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
                $out[] = self::run( $child->nodeValue, $bold, $italic, $href );
            } elseif ( $child instanceof DOMElement ) {
                $this->element_runs( $child, $bold, $italic, $href, $out );
            }
        }
        return $out;
    }

    private function element_runs( DOMElement $el, bool $bold, bool $italic, ?string $href, array &$out ) {
        $tag = strtolower( $el->tagName );
        if ( in_array( $tag, self::SKIP, true ) || in_array( $tag, array( 'ul', 'ol', 'table', 'form' ), true ) || self::hidden( $el ) ) {
            return;
        }
        switch ( $tag ) {
            case 'br':
                $out[] = array( 't' => "\n", 'b' => $bold, 'i' => $italic, 'href' => $href );
                return;
            case 'strong':
            case 'b':
                $bold = true;
                break;
            case 'em':
            case 'i':
                $italic = true;
                break;
            case 'a':
                $href = $this->url( $el->getAttribute( 'href' ) ) ?? $href;
                break;
        }
        $this->runs( $el, $bold, $italic, $href, $out );
    }

    /** Merge neighbouring runs that look the same, tidy spaces, drop empties. */
    private function tidy( array $runs ): array {
        $merged = array();
        foreach ( $runs as $r ) {
            if ( '' === $r['t'] ) {
                continue;
            }
            $last = count( $merged ) - 1;
            if ( $last >= 0 && $merged[ $last ]['b'] === $r['b'] && $merged[ $last ]['i'] === $r['i'] && $merged[ $last ]['href'] === $r['href'] ) {
                $merged[ $last ]['t'] .= $r['t'];
            } else {
                // One space between runs, not two.
                if ( $last >= 0 && ' ' === substr( $merged[ $last ]['t'], -1 ) ) {
                    $r['t'] = ltrim( $r['t'], ' ' );
                    if ( '' === $r['t'] ) {
                        continue;
                    }
                }
                $merged[] = $r;
            }
        }

        $out   = array();
        $count = count( $merged );
        foreach ( $merged as $n => $r ) {
            $t = preg_replace( '/ *\n */', "\n", preg_replace( '/ {2,}/', ' ', $r['t'] ) );
            if ( 0 === $n ) {
                $t = ltrim( $t, " \n" );
            }
            if ( $count - 1 === $n ) {
                $t = rtrim( $t, " \n" );
            }
            if ( '' === $t ) {
                continue;
            }
            $item = array( 't' => $t );
            if ( $r['b'] ) {
                $item['b'] = true;
            }
            if ( $r['i'] ) {
                $item['i'] = true;
            }
            if ( null !== $r['href'] ) {
                $item['href'] = $r['href'];
            }
            $out[] = $item;
        }
        return '' === trim( implode( '', array_column( $out, 't' ) ) ) ? array() : $out;
    }

    private function text_of( DOMNode $node ): string {
        return implode( '', array_column( $this->tidy( $this->runs( $node ) ), 't' ) );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** No-break and zero-width spaces out, runs of whitespace to one space. */
    private static function norm( string $text ): string {
        $text = str_replace( array( "\xC2\xA0", "\xE2\x80\x8B", "\xEF\xBB\xBF" ), array( ' ', '', '' ), $text );
        return (string) preg_replace( '/[ \t\r\n\f\v]+/u', ' ', $text );
    }

    private static function one_line( string $text ): string {
        return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
    }

    private static function has_class( DOMElement $el, string $class ): bool {
        return false !== strpos( ' ' . self::norm( $el->getAttribute( 'class' ) ) . ' ', ' ' . $class . ' ' );
    }

    private static function hidden( DOMElement $el ): bool {
        if ( $el->hasAttribute( 'hidden' ) || self::has_class( $el, 'hidden' ) || self::has_class( $el, 'screen-reader-text' ) ) {
            return true;
        }
        return false !== strpos( strtolower( str_replace( ' ', '', $el->getAttribute( 'style' ) ) ), 'display:none' );
    }

    private function find_class( DOMElement $el, string $class ): ?DOMElement {
        $found = $this->xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]', $el );
        return ( $found && $found->length && $found->item( 0 ) instanceof DOMElement ) ? $found->item( 0 ) : null;
    }

    private function first( DOMElement $el, string $tag ): ?DOMElement {
        $found = $el->getElementsByTagName( $tag );
        return $found->length ? $found->item( 0 ) : null;
    }

    /** The image's URL: the srcset size nearest 800px wide if there is one, else its src. */
    private function image_src( DOMElement $img ): ?string {
        $best   = null;
        $best_w = 0;
        $srcset = $img->getAttribute( 'srcset' ) ?: $img->getAttribute( 'data-srcset' );
        foreach ( explode( ',', $srcset ) as $candidate ) {
            $bits = preg_split( '/\s+/', trim( $candidate ) );
            if ( 2 === count( $bits ) && preg_match( '/^(\d+)w$/', $bits[1], $m ) ) {
                $w = (int) $m[1];
                if ( $w >= 600 && $w <= 1200 && ( null === $best || abs( $w - 800 ) < abs( $best_w - 800 ) ) ) {
                    $best   = $bits[0];
                    $best_w = $w;
                }
            }
        }
        $src = $best ?? ( $img->getAttribute( 'data-src' ) ?: $img->getAttribute( 'src' ) );
        if ( '' === $src || 0 === stripos( $src, 'data:' ) || preg_match( '/\.svg(\?|$)/i', $src ) ) {
            return null;
        }
        return $this->url( $src );
    }

    /** Absolute http(s), mailto or tel URL, or null for anchors, scripts and junk. */
    private function url( string $href ): ?string {
        $href = trim( $href );
        if ( '' === $href || '#' === $href[0] || 0 === stripos( $href, 'javascript:' ) ) {
            return null;
        }
        if ( preg_match( '/^(mailto|tel):/i', $href ) ) {
            $href = esc_url_raw( $href, array( 'mailto', 'tel' ) );
            return '' === $href ? null : $href;
        }
        if ( 0 === strpos( $href, '//' ) ) {
            $href = 'https:' . $href;
        } elseif ( ! preg_match( '#^https?://#i', $href ) ) {
            $href = $this->base . ltrim( $href, '/' );
        }
        $href = esc_url_raw( $href, array( 'http', 'https' ) );
        return '' === $href ? null : $href;
    }
}
