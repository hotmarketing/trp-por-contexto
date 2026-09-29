<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TRP_CO_Override_Engine {

    private $overrides = null;

    public function __construct() {
        // Hook into TranslatePress's own filter that fires AFTER all translation
        // processing is complete. This gives us the final translated HTML.
        add_filter( 'trp_translated_html', array( $this, 'apply_overrides' ), 10, 3 );
    }

    /**
     * Apply contextual overrides to the translated HTML.
     *
     * @param string $html           The fully translated HTML.
     * @param string $TRP_LANGUAGE   Current language (e.g. en_US).
     * @param string $language_code  Language code (e.g. en).
     * @return string
     */
    public function apply_overrides( $html, $TRP_LANGUAGE = '', $language_code = '' ) {
        if ( empty( $html ) ) {
            return $html;
        }

        $page_id = $this->get_current_page_id();

        // Debug mode: add ?trp_co_debug=1 to any frontend URL to see debug info.
        // Solo para administradores: expone page_id, overrides y fragmentos del HTML.
        $debug = isset( $_GET['trp_co_debug'] ) && $_GET['trp_co_debug'] === '1' && current_user_can( 'manage_options' );
        $debug_log = array();

        if ( $debug ) {
            $debug_log[] = 'page_id detected: ' . ( $page_id ? $page_id : 'NONE' );
            $debug_log[] = 'language: ' . $language_code;
        }

        if ( ! $page_id ) {
            if ( $debug ) {
                $html .= "\n<!-- TRP_CO DEBUG: No page_id detected -->\n";
            }
            return $html;
        }

        $overrides = $this->get_overrides_for_page( $page_id );

        if ( $debug ) {
            $debug_log[] = 'overrides found: ' . count( $overrides );
        }

        if ( empty( $overrides ) ) {
            if ( $debug ) {
                $html .= "\n<!-- TRP_CO DEBUG: page_id={$page_id}, no overrides for this page -->\n";
            }
            return $html;
        }

        foreach ( $overrides as $override ) {
            $type = isset( $override->override_type ) ? $override->override_type : 'string';

            if ( $type === 'selector' ) {
                // Selector mode: find element by ID and replace its innerHTML.
                $element_id = trim( $override->original );
                $result     = $this->replace_element_inner_html( $html, $element_id, $override->translated );
                $found      = ( $result !== $html );
                $html       = $result;

                if ( $debug ) {
                    $debug_log[] = 'override #' . $override->id
                        . ' [SELECTOR] | id="' . $element_id . '"'
                        . ' | match: ' . ( $found ? 'YES' : 'NO' );
                }
            } else {
                // String mode: exact str_replace (original behavior).
                $found = strpos( $html, $override->original ) !== false;
                if ( $debug ) {
                    $debug_log[] = 'override #' . $override->id
                        . ' [STRING] | match: ' . ( $found ? 'YES' : 'NO' )
                        . ' | looking for: [' . mb_substr( $override->original, 0, 80 ) . ']';

                    if ( ! $found ) {
                        $search_text = trim( strip_tags( $override->original ) );
                        $pos = stripos( $html, $search_text );
                        if ( $pos !== false ) {
                            $context = substr( $html, max( 0, $pos - 30 ), strlen( $search_text ) + 60 );
                            $debug_log[] = 'found similar text at pos ' . $pos;
                            $debug_log[] = 'html context: [' . htmlspecialchars( $context ) . ']';
                        } else {
                            $debug_log[] = 'plain text "' . $search_text . '" NOT found in HTML either';
                        }
                    }
                }
                $html = str_replace( $override->original, $override->translated, $html );
            }
        }

        if ( $debug ) {
            // "--" no puede aparecer dentro de un comentario HTML: evita cerrarlo antes de tiempo.
            $html .= "\n<!-- TRP_CO DEBUG:\n" . str_replace( '--', '- -', implode( "\n", $debug_log ) ) . "\n-->\n";
        }

        return $html;
    }

    /**
     * Replace the innerHTML of an element found by its ID attribute.
     * Handles nested tags of the same type by counting open/close tags.
     *
     * @param string $html           Full HTML string.
     * @param string $element_id     The ID attribute value to search for.
     * @param string $new_inner_html The replacement innerHTML.
     * @return string Modified HTML, or original if element not found.
     */
    private function replace_element_inner_html( $html, $element_id, $new_inner_html ) {
        $escaped_id = preg_quote( $element_id, '/' );

        // Match the opening tag that contains id="element_id".
        // Captures: (1) the tag name, (0) the full opening tag.
        $pattern = '/<([a-z][a-z0-9]*)\b([^>]*?\bid\s*=\s*["\']' . $escaped_id . '["\'][^>]*)>/is';

        if ( ! preg_match( $pattern, $html, $matches, PREG_OFFSET_CAPTURE ) ) {
            return $html;
        }

        $tag_name       = strtolower( $matches[1][0] );
        $open_tag_start = $matches[0][1];
        $open_tag_full  = $matches[0][0];
        $inner_start    = $open_tag_start + strlen( $open_tag_full );

        // Walk through the HTML from inner_start, counting nested open/close
        // tags of the same type to find the matching closing tag.
        $depth  = 1;
        $pos    = $inner_start;
        $length = strlen( $html );

        // Patterns for open and close tags of the same type.
        $open_pattern  = '/<' . preg_quote( $tag_name, '/' ) . '\b[^>]*>/i';
        $close_pattern = '/<\/' . preg_quote( $tag_name, '/' ) . '\s*>/i';

        while ( $depth > 0 && $pos < $length ) {
            $next_open  = preg_match( $open_pattern, $html, $m_open, PREG_OFFSET_CAPTURE, $pos ) ? $m_open[0][1] : PHP_INT_MAX;
            $next_close = preg_match( $close_pattern, $html, $m_close, PREG_OFFSET_CAPTURE, $pos ) ? $m_close[0][1] : PHP_INT_MAX;

            if ( $next_close === PHP_INT_MAX ) {
                // No closing tag found — malformed HTML, bail out.
                return $html;
            }

            if ( $next_open < $next_close ) {
                $depth++;
                $pos = $next_open + strlen( $m_open[0][0] );
            } else {
                $depth--;
                if ( $depth === 0 ) {
                    // Found the matching closing tag.
                    $inner_end = $next_close;
                    return substr( $html, 0, $inner_start )
                         . $new_inner_html
                         . substr( $html, $inner_end );
                }
                $pos = $next_close + strlen( $m_close[0][0] );
            }
        }

        return $html;
    }

    /**
     * Determine the current page/post ID.
     */
    private function get_current_page_id() {
        $id = get_queried_object_id();
        if ( $id ) {
            return $id;
        }

        return get_the_ID();
    }

    /**
     * Get overrides for a given page, cached for the duration of the request.
     */
    private function get_overrides_for_page( $page_id ) {
        if ( $this->overrides === null ) {
            $this->overrides = TRP_CO_Database::get_overrides( $page_id );
        }

        return $this->overrides;
    }
}
