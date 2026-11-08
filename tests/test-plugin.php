<?php
/**
 * Offline test: loads the audit engine with stubbed WordPress functions
 * and checks scoring on a good page, a thin page, and edge cases.
 * Run: php tests/test-plugin.php
 */
error_reporting( E_ALL );

define( 'ABSPATH', '/tmp/wp-stub/' );

function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function __( $s, $d = null ) { return $s; }

require dirname( __DIR__ ) . '/includes/class-nzsa-audit.php';

function check( $label, $cond ) {
    echo ( $cond ? "PASS" : "FAIL" ) . ": $label\n";
    if ( ! $cond ) {
        $GLOBALS['nzsa_failed'] = true;
    }
}

// A strong paragraph, repeated to reach 400+ words, short sentences.
$para = 'WordPress development is a craft. A good developer builds fast themes. '
      . 'Clean code matters for every site. Clients want pages that load quickly. '
      . 'Search engines reward clear structure. This guide covers the basics. ';
$good_content = '<h1>WordPress Developer Guide for Modern Websites</h1>'
    . '<p>' . str_repeat( $para, 6 ) . 'WordPress developer skills make the difference here.</p>'
    . '<h2>Custom Themes</h2><p>' . str_repeat( $para, 3 ) . '</p>'
    . '<h2>Plugins and Performance</h2><p>' . str_repeat( $para, 3 ) . '</p>'
    . '<h3>Speed Tips</h3><p>' . str_repeat( $para, 2 ) . '</p>'
    . '<p><img src="a.jpg" alt="WordPress dashboard" /> <img src="b.jpg" alt="Fast website" /></p>'
    . '<p>Read our <a href="https://nzgenix.digital/services/">services page</a> and the '
    . '<a href="https://wordpress.org/">official WordPress site</a> for more.</p>';

$good = NZSA_Audit::run( array(
    'title'            => 'WordPress Developer Guide for Modern Websites',
    'content'          => $good_content,
    'meta_description' => 'A practical WordPress developer guide covering custom themes, plugins, speed and SEO for modern business websites in 2026.',
    'keyword'          => 'wordpress developer',
    'slug'             => 'wordpress-developer-guide',
    'site_url'         => 'https://nzgenix.digital/',
    'min_word_count'   => 300,
    'max_sentence_words' => 20,
) );

check( 'good page scores 80+ (got ' . $good['score'] . ')', $good['score'] >= 80 );
check( 'good page grade is good', 'good' === $good['grade'] );
check( 'good page title check passes', 'pass' === $good['checks']['title_length']['status'] );
check( 'good page meta check passes', 'pass' === $good['checks']['meta_description']['status'] );
check( 'good page keyword in title passes', 'pass' === $good['checks']['keyword_in_title']['status'] );
check( 'good page H1 check passes', 'pass' === $good['checks']['h1_count']['status'] );
check( 'good page images alt passes', 'pass' === $good['checks']['images_alt']['status'] );
check( 'good page links check passes', 'pass' === $good['checks']['links']['status'] );

$thin = NZSA_Audit::run( array(
    'title'            => 'Hi',
    'content'          => '<p>Short text here.</p><p><img src="x.jpg" /></p>',
    'meta_description' => '',
    'keyword'          => 'wordpress developer',
    'slug'             => 'hi',
    'site_url'         => 'https://nzgenix.digital/',
    'min_word_count'   => 300,
    'max_sentence_words' => 20,
) );

check( 'thin page scores below 50 (got ' . $thin['score'] . ')', $thin['score'] < 50 );
check( 'thin page grade is poor', 'poor' === $thin['grade'] );
check( 'thin page meta check fails', 'fail' === $thin['checks']['meta_description']['status'] );
check( 'thin page H1 check fails', 'fail' === $thin['checks']['h1_count']['status'] );
check( 'thin page images alt fails', 'fail' === $thin['checks']['images_alt']['status'] );
check( 'thin page word count fails', 'fail' === $thin['checks']['word_count']['status'] );
check( 'thin page links check fails', 'fail' === $thin['checks']['links']['status'] );

$no_keyword = NZSA_Audit::run( array(
    'title'            => 'A Properly Sized Title for This Test Page Here',
    'content'          => '<h1>Heading</h1><p>' . str_repeat( $para, 10 ) . '</p>',
    'meta_description' => str_repeat( 'x', 140 ),
    'keyword'          => '',
    'slug'             => 'test',
    'site_url'         => 'https://nzgenix.digital/',
    'min_word_count'   => 300,
    'max_sentence_words' => 20,
) );
check( 'no keyword warns on keyword checks (not fail)', 'warn' === $no_keyword['checks']['keyword_in_title']['status'] && 'warn' === $no_keyword['checks']['keyword_in_content']['status'] );

check( 'word_count counts plain words', 5 === NZSA_Audit::word_count( 'one two three four five' ) );
check( 'word_count ignores HTML', 3 === NZSA_Audit::word_count( '<p>one <strong>two</strong> three</p>' ) );
check( 'avg sentence length measured', abs( NZSA_Audit::avg_sentence_length( 'One two three. Four five six seven.' ) - 3.5 ) < 0.01 );
check( 'grade bands correct', 'good' === NZSA_Audit::grade( 80 ) && 'needs-work' === NZSA_Audit::grade( 65 ) && 'poor' === NZSA_Audit::grade( 49 ) );
check( 'ten checks defined, weights sum to 100', 10 === count( NZSA_Audit::checks() ) && 100 === array_sum( array_column( NZSA_Audit::checks(), 'weight' ) ) );

$lines = 0;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ), FilesystemIterator::SKIP_DOTS ) ) as $f ) {
    if ( preg_match( '/\.(php|js|css)$/', $f->getFilename() ) && ! str_contains( $f->getPathname(), '/tests/' ) ) {
        $lines += count( file( $f->getPathname() ) );
    }
}
echo "Plugin code lines (PHP/JS/CSS, excl. tests): $lines\n";
check( 'plugin exceeds 300 lines of code', $lines > 300 );

echo ( $GLOBALS['nzsa_failed'] ?? false ) ? "RESULT: FAILED\n" : "RESULT: ALL PASSED\n";
exit( ( $GLOBALS['nzsa_failed'] ?? false ) ? 1 : 0 );
