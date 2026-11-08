<?php
/**
 * The audit engine: pure logic, no WordPress calls inside the checks
 * themselves (only the caller fetches post data). That keeps every check
 * unit-testable with plain arrays — see tests/test-plugin.php.
 *
 * Scoring: each check is worth its weight; a check returns pass (full
 * weight), warn (half weight) or fail (0). Total is normalised to 0–100.
 * Grade bands: 80+ Good, 50–79 Needs work, below 50 Poor.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZSA_Audit {

    /**
     * All checks, in the order they appear in the report.
     * weight = points at stake; the sum of weights is 100.
     */
    public static function checks() {
        return array(
            'title_length'       => array( 'weight' => 12, 'label' => 'Title length (30–60 characters)' ),
            'meta_description'   => array( 'weight' => 12, 'label' => 'Meta description present (120–160 characters)' ),
            'keyword_in_title'   => array( 'weight' => 10, 'label' => 'Target keyword in the title' ),
            'keyword_in_content' => array( 'weight' => 10, 'label' => 'Target keyword in the first paragraph' ),
            'h1_count'           => array( 'weight' => 10, 'label' => 'Exactly one H1 heading' ),
            'headings_structure' => array( 'weight' => 8,  'label' => 'Headings used (H2/H3) to structure the content' ),
            'word_count'         => array( 'weight' => 10, 'label' => 'Content length (300+ words)' ),
            'images_alt'         => array( 'weight' => 10, 'label' => 'Every image has alt text' ),
            'links'              => array( 'weight' => 8,  'label' => 'At least one internal and one external link' ),
            'readability'        => array( 'weight' => 10, 'label' => 'Readable sentences (average under 20 words)' ),
        );
    }

    /**
     * Run the audit on a post's data.
     *
     * @param array $data title, content (HTML), excerpt/meta description,
     *                    keyword, slug, word_count override (optional).
     * @return array score (0–100), grade, checks[] with status + detail.
     */
    public static function run( array $data ) {
        $title    = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';
        $content  = isset( $data['content'] ) ? (string) $data['content'] : '';
        $meta     = isset( $data['meta_description'] ) ? trim( (string) $data['meta_description'] ) : '';
        $keyword  = isset( $data['keyword'] ) ? trim( mb_strtolower( (string) $data['keyword'] ) ) : '';
        $plain    = trim( wp_strip_all_tags( $content ) );
        $words    = self::word_count( $plain );
        $checks   = array();
        $earned   = 0;
        $possible = 0;

        foreach ( self::checks() as $key => $def ) {
            $result = self::check( $key, $title, $content, $plain, $meta, $keyword, $words, $data );
            $possible += $def['weight'];
            $earned   += ( 'pass' === $result['status'] ) ? $def['weight'] : ( ( 'warn' === $result['status'] ) ? $def['weight'] / 2 : 0 );
            $checks[ $key ] = array(
                'label'  => $def['label'],
                'status' => $result['status'],
                'detail' => $result['detail'],
                'weight' => $def['weight'],
            );
        }

        $score = $possible ? (int) round( $earned / $possible * 100 ) : 0;
        return array(
            'score'  => $score,
            'grade'  => self::grade( $score ),
            'checks' => $checks,
        );
    }

    public static function grade( $score ) {
        if ( $score >= 80 ) {
            return 'good';
        }
        if ( $score >= 50 ) {
            return 'needs-work';
        }
        return 'poor';
    }

    public static function grade_label( $grade ) {
        $labels = array(
            'good'       => 'Good',
            'needs-work' => 'Needs work',
            'poor'       => 'Poor',
        );
        return isset( $labels[ $grade ] ) ? $labels[ $grade ] : 'Unknown';
    }

    /** Word count that ignores extra whitespace and HTML entities. */
    public static function word_count( $plain_text ) {
        $plain_text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $plain_text, ENT_QUOTES, 'UTF-8' ) ) );
        if ( '' === $plain_text ) {
            return 0;
        }
        return count( preg_split( '/\s+/u', $plain_text ) );
    }

    /** Average words per sentence, for the readability check. */
    public static function avg_sentence_length( $plain_text ) {
        $sentences = preg_split( '/[.!?]+/u', $plain_text, -1, PREG_SPLIT_NO_EMPTY );
        $sentences = array_filter( array_map( 'trim', $sentences ) );
        if ( empty( $sentences ) ) {
            return 0;
        }
        return self::word_count( $plain_text ) / count( $sentences );
    }

    private static function check( $key, $title, $content, $plain, $meta, $keyword, $words, $data ) {
        switch ( $key ) {
            case 'title_length':
                $len = mb_strlen( $title );
                if ( $len >= 30 && $len <= 60 ) {
                    return array( 'status' => 'pass', 'detail' => "Title is {$len} characters — in the 30–60 range." );
                }
                if ( $len > 0 && $len < 30 ) {
                    return array( 'status' => 'warn', 'detail' => "Title is only {$len} characters — aim for 30–60." );
                }
                if ( $len > 60 ) {
                    return array( 'status' => 'warn', 'detail' => "Title is {$len} characters — it may be cut off in search results; aim for 30–60." );
                }
                return array( 'status' => 'fail', 'detail' => 'No title set.' );

            case 'meta_description':
                $len = mb_strlen( $meta );
                if ( $len >= 120 && $len <= 160 ) {
                    return array( 'status' => 'pass', 'detail' => "Meta description is {$len} characters — in the 120–160 range." );
                }
                if ( $len > 0 ) {
                    return array( 'status' => 'warn', 'detail' => "Meta description is {$len} characters — aim for 120–160." );
                }
                return array( 'status' => 'fail', 'detail' => 'No meta description found — search results will show an auto-generated snippet.' );

            case 'keyword_in_title':
                if ( '' === $keyword ) {
                    return array( 'status' => 'warn', 'detail' => 'No target keyword set for this post — set one in the SEO Audit box to enable keyword checks.' );
                }
                if ( false !== mb_stripos( $title, $keyword ) ) {
                    return array( 'status' => 'pass', 'detail' => "Keyword '{$keyword}' appears in the title." );
                }
                return array( 'status' => 'fail', 'detail' => "Keyword '{$keyword}' is missing from the title." );

            case 'keyword_in_content':
                if ( '' === $keyword ) {
                    return array( 'status' => 'warn', 'detail' => 'No target keyword set for this post.' );
                }
                $first_para = self::first_paragraph( $content );
                if ( '' !== $first_para && false !== mb_stripos( $first_para, $keyword ) ) {
                    return array( 'status' => 'pass', 'detail' => "Keyword '{$keyword}' appears in the first paragraph." );
                }
                if ( false !== mb_stripos( $plain, $keyword ) ) {
                    return array( 'status' => 'warn', 'detail' => "Keyword '{$keyword}' appears in the content, but not in the first paragraph." );
                }
                return array( 'status' => 'fail', 'detail' => "Keyword '{$keyword}' does not appear in the content at all." );

            case 'h1_count':
                $count = preg_match_all( '/<h1[\s>]/i', $content );
                if ( 1 === $count ) {
                    return array( 'status' => 'pass', 'detail' => 'Exactly one H1 heading.' );
                }
                if ( 0 === $count ) {
                    return array( 'status' => 'fail', 'detail' => 'No H1 heading found — add one main heading.' );
                }
                return array( 'status' => 'warn', 'detail' => "{$count} H1 headings found — keep exactly one main heading." );

            case 'headings_structure':
                $h2 = preg_match_all( '/<h2[\s>]/i', $content );
                $h3 = preg_match_all( '/<h3[\s>]/i', $content );
                if ( $h2 >= 2 ) {
                    return array( 'status' => 'pass', 'detail' => "{$h2} H2 headings structure the content." . ( $h3 ? " Plus {$h3} H3 sub-headings." : '' ) );
                }
                if ( $h2 === 1 || $h3 >= 1 ) {
                    return array( 'status' => 'warn', 'detail' => 'Only one sub-heading found — break long content into sections with H2 headings.' );
                }
                return array( 'status' => 'fail', 'detail' => 'No H2/H3 headings — the content is one unbroken block.' );

            case 'word_count':
                $min = isset( $data['min_word_count'] ) ? (int) $data['min_word_count'] : 300;
                if ( $words >= $min ) {
                    return array( 'status' => 'pass', 'detail' => "{$words} words — above the {$min}-word minimum." );
                }
                if ( $words >= 150 ) {
                    return array( 'status' => 'warn', 'detail' => "Only {$words} words — aim for at least {$min} for a competitive page." );
                }
                return array( 'status' => 'fail', 'detail' => "Only {$words} words — thin content rarely ranks; aim for at least {$min}." );

            case 'images_alt':
                preg_match_all( '/<img\b[^>]*>/i', $content, $imgs );
                $total = count( $imgs[0] );
                if ( 0 === $total ) {
                    return array( 'status' => 'warn', 'detail' => 'No images in the content — images with descriptive alt text help SEO and readers.' );
                }
                $missing = 0;
                foreach ( $imgs[0] as $img ) {
                    if ( ! preg_match( '/\salt\s*=\s*["\'][^"\']+["\']/i', $img ) ) {
                        $missing++;
                    }
                }
                if ( 0 === $missing ) {
                    return array( 'status' => 'pass', 'detail' => "All {$total} image(s) have alt text." );
                }
                return array( 'status' => 'fail', 'detail' => "{$missing} of {$total} image(s) are missing alt text." );

            case 'links':
                preg_match_all( '/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $content, $links );
                $site = isset( $data['site_url'] ) ? (string) $data['site_url'] : '';
                $internal = 0;
                $external = 0;
                foreach ( $links[1] as $href ) {
                    if ( '' === $href || '#' === $href[0] ) {
                        continue;
                    }
                    if ( $site && 0 === strpos( $href, $site ) ) {
                        $internal++;
                    } elseif ( 0 === strpos( $href, 'http' ) ) {
                        $external++;
                    } else {
                        $internal++; // Relative link = internal.
                    }
                }
                if ( $internal >= 1 && $external >= 1 ) {
                    return array( 'status' => 'pass', 'detail' => "{$internal} internal link(s) and {$external} external link(s)." );
                }
                if ( $internal >= 1 || $external >= 1 ) {
                    return array( 'status' => 'warn', 'detail' => "Only " . ( $internal ? 'internal' : 'external' ) . ' link(s) found — add the other type.' );
                }
                return array( 'status' => 'fail', 'detail' => 'No links in the content.' );

            case 'readability':
                $avg = self::avg_sentence_length( $plain );
                $max = isset( $data['max_sentence_words'] ) ? (int) $data['max_sentence_words'] : 20;
                if ( 0 === $avg ) {
                    return array( 'status' => 'fail', 'detail' => 'No sentences found to measure.' );
                }
                if ( $avg <= $max ) {
                    return array( 'status' => 'pass', 'detail' => sprintf( 'Average sentence length is %.1f words — under the %d-word target.', $avg, $max ) );
                }
                return array( 'status' => 'warn', 'detail' => sprintf( 'Average sentence length is %.1f words — shorten long sentences toward %d words.', $avg, $max ) );
        }
        return array( 'status' => 'fail', 'detail' => 'Check not implemented.' );
    }

    /** Plain text of the first paragraph (or first 300 chars as fallback). */
    private static function first_paragraph( $content ) {
        if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
            return wp_strip_all_tags( $m[1] );
        }
        return wp_strip_all_tags( mb_substr( $content, 0, 300 ) );
    }
}
