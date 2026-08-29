<?php
/**
 * Great Lake Cleaners — functions.php
 * Theme setup: nav menus, featured images, font enqueue, body class helpers.
 */

defined( 'ABSPATH' ) || exit;

define( 'GLC_THEME_VERSION', '1.4.5' );

// PayPal Pool fundraiser — cigarette butt dispensers at trail heads.
// Used by the header + footer donate icons and the NGO JSON-LD DonateAction.
define( 'GLC_DONATE_URL', 'https://www.paypal.com/pools/c/9rTJrg2a4B' );

// ── Theme setup ───────────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function() {

    load_theme_textdomain( 'great-lake-cleaners', get_stylesheet_directory() . '/languages' );

	remove_action('wp_head', 'print_emoji_detection_script', 7);
	remove_action('wp_print_styles', 'print_emoji_styles');

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'gallery', 'caption', 'navigation-widgets' ] );
    add_theme_support( 'custom-logo', [
        'height'      => 200,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    register_nav_menus( [
        'primary' => __( 'Primary Navigation', 'great-lake-cleaners' ),
        'footer'  => __( 'Footer Navigation',  'great-lake-cleaners' ),
    ] );
} );

// ── Enqueue styles ────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function() {

    // Main stylesheet — fonts are self-hosted via @font-face in style.css
    wp_enqueue_style(
        'glc-style',
        get_stylesheet_uri(),
        [],
        GLC_THEME_VERSION
    );

    // Mobile nav toggle + compact header
    wp_enqueue_script(
        'glc-nav',
        get_stylesheet_directory_uri() . '/assets/js/nav.js',
        [],
        GLC_THEME_VERSION,
        true
    );

    // Footer stats count-up animation
    wp_enqueue_script(
        'glc-stats-counter',
        get_stylesheet_directory_uri() . '/assets/js/stats-counter.js',
        [],
        GLC_THEME_VERSION,
        true
    );

} );

// ── Nav fallback — renders basic links if no menu is assigned ─────────────────
function glc_nav_fallback() {
    echo '<ul class="glc-nav-menu glc-nav-fallback">';
    echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">'
        . esc_html__( 'Home', 'great-lake-cleaners' ) . '</a></li>';

    $archive = get_post_type_archive_link( 'cleanup_event' );
    if ( $archive ) {
        echo '<li><a href="' . esc_url( $archive ) . '">'
            . esc_html__( 'Cleanups', 'great-lake-cleaners' ) . '</a></li>';
    }

    $events = get_post_type_archive_link( 'glc_event' );
    if ( $events ) {
        echo '<li><a href="' . esc_url( $events ) . '">'
            . esc_html__( 'Events', 'great-lake-cleaners' ) . '</a></li>';
    }

    $media = get_page_by_path( 'see-us-in-action' );
    if ( $media ) {
        echo '<li><a href="' . esc_url( get_permalink( $media ) ) . '">'
            . esc_html__( 'Crew at Work', 'great-lake-cleaners' ) . '</a></li>';
    }

    $about = get_page_by_path( 'about' );
    if ( $about ) {
        echo '<li><a href="' . esc_url( get_permalink( $about ) ) . '">'
            . esc_html__( 'About', 'great-lake-cleaners' ) . '</a></li>';
    }
    echo '</ul>';
}

// ── Body classes ──────────────────────────────────────────────────────────────

// ── Shared impact stats helper ────────────────────────────────────────────────
/**
 * Returns an array of cumulative impact totals across all published cleanup
 * events and approved community submissions. Used by the front-page stats strip
 * and the archive-cleanup_event.php stat cards.
 *
 * @return array {
 *   int    $cleanups   Total cleanup events + approved submissions
 *   float  $weight_kg  Total debris weight
 *   float  $hours      Total volunteer person-hours
 *   int    $recycled   Total items recycled (0 if none logged)
 *   int    $corridors  Distinct corridor values across cleanup_event + glc_submission posts
 * }
 */
function glc_get_impact_stats(): array {
    $event_ids = get_posts( [
        'post_type'      => 'cleanup_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    $weight     = 0.0;
    $hours      = 0.0;
    $recycled   = 0;
    $corridors  = [];

    foreach ( $event_ids as $id ) {
        $weight   += (float) get_post_meta( $id, 'weight_kg',      true );
        $hours    += (float) get_post_meta( $id, 'hours',          true );
        $recycled += (int)   get_post_meta( $id, 'items_recycled', true );
        $c = trim( (string) get_post_meta( $id, 'corridor', true ) );
        if ( $c !== '' ) {
            $corridors[ strtolower( $c ) ] = true;
        }
    }

    $community = get_posts( [
        'post_type'      => 'glc_submission',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $community as $id ) {
        $weight   += (float) get_post_meta( $id, 'weight_kg',      true );
        $hours    += (float) get_post_meta( $id, 'glc_hours',      true );
        $recycled += (int)   get_post_meta( $id, 'items_recycled', true );
        $c = trim( (string) get_post_meta( $id, 'glc_corridor', true ) );
        if ( $c !== '' ) {
            $corridors[ strtolower( $c ) ] = true;
        }
    }

    return [
        'cleanups'   => count( $event_ids ) + count( $community ),
        'weight_kg'  => $weight,
        'hours'      => $hours,
        'recycled'   => $recycled,
        'corridors'  => count( $corridors ),
    ];
}

add_filter( 'body_class', function( $classes ) {
    if ( is_singular( 'cleanup_event' ) ) {
        $classes[] = 'glc-single-cleanup';
    }
    if ( is_post_type_archive( 'cleanup_event' ) ) {
        $classes[] = 'glc-archive-cleanup';
    }
    if ( is_singular( 'glc_event' ) ) {
        $classes[] = 'glc-single-event-page';
    }
    if ( is_post_type_archive( 'glc_event' ) ) {
        $classes[] = 'glc-archive-event';
    }
    return $classes;
} );

// ── SVG area chart helpers ────────────────────────────────────────────────────
// Shared by page-stats.php, [glc_timeline], and [glc_impact_highlights].

function glc_stats_smooth_path( $pts ) {
    if ( count( $pts ) < 2 ) return '';
    $t = 0.16;
    $n = count( $pts );
    $d = sprintf( 'M%.2f %.2f', $pts[0][0], $pts[0][1] );
    for ( $i = 0; $i < $n - 1; $i++ ) {
        $p0  = ( $i > 0 ) ? $pts[ $i - 1 ] : $pts[ $i ];
        $p1  = $pts[ $i ];
        $p2  = $pts[ $i + 1 ];
        $p3  = ( $i + 2 < $n ) ? $pts[ $i + 2 ] : $p2;
        $c1x = $p1[0] + ( $p2[0] - $p0[0] ) * $t;
        $c1y = $p1[1] + ( $p2[1] - $p0[1] ) * $t;
        $c2x = $p2[0] - ( $p3[0] - $p1[0] ) * $t;
        $c2y = $p2[1] - ( $p3[1] - $p1[1] ) * $t;
        $d  .= sprintf( ' C%.2f %.2f %.2f %.2f %.2f %.2f', $c1x, $c1y, $c2x, $c2y, $p2[0], $p2[1] );
    }
    return $d;
}

/**
 * Render a multi-series cumulative area chart as inline SVG.
 *
 * Each series is independently scaled to a "nice" axis max so that the two
 * endpoint circles land at different heights — making the different scales
 * visually obvious rather than converging to the same point.
 *
 * @param array $series     [ [ 'key', 'color', 'values', 'max', 'fill_opacity' ], … ]
 * @param int   $height     SVG logical height
 * @param array $days       Day-offset array (same index as values)
 * @param int   $max_day    Day offset of the last data point
 * @param int   $first_ts   Unix timestamp of day 0 (for x-axis labels)
 * @param bool  $show_axes  When true, render left/right Y-axis tick labels
 */
function glc_stats_area_chart( $series, $height, $days, $max_day, $first_ts = 0, $show_axes = false ) {
    if ( empty( $days ) || $max_day <= 0 ) return '';

    $W     = 1000;
    $H     = $height;
    $padL  = $show_axes ? 54 : 14;
    $padR  = ( $show_axes && count( $series ) >= 2 ) ? 54 : 14;
    $padT  = 26;
    $padB  = 34;
    $plotH = $H - $padT - $padB;
    $baseY = $H - $padB;

    // Returns a "nice" axis ceiling slightly above $v.
    $nice_max_fn = function( $v ) {
        if ( $v <= 0 ) return 10;
        $e = floor( log10( $v ) );
        $m = pow( 10, $e );
        $f = $v / $m;
        if ( $f < 1.2 ) return 1.5 * $m;
        if ( $f < 1.8 ) return 2.0 * $m;
        if ( $f < 2.3 ) return 2.5 * $m;
        if ( $f < 2.8 ) return 3.0 * $m;
        if ( $f < 3.8 ) return 4.0 * $m;
        if ( $f < 4.8 ) return 5.0 * $m;
        if ( $f < 5.8 ) return 6.0 * $m;
        if ( $f < 7.8 ) return 8.0 * $m;
        return 10.0 * $m;
    };

    // Returns a "nice" tick interval for a given axis ceiling.
    $nice_step_fn = function( $nm ) {
        $r = $nm / 4;
        $e = floor( log10( max( $r, 1 ) ) );
        $m = pow( 10, $e );
        $n = $r / $m;
        if ( $n < 1.5 ) return $m;
        if ( $n < 3.5 ) return 2 * $m;
        if ( $n < 7.5 ) return 5 * $m;
        return 10 * $m;
    };

    $X = function( $d ) use ( $padL, $padR, $W, $max_day ) {
        return $padL + ( $d / $max_day ) * ( $W - $padL - $padR );
    };

    // Build each series: always use a "nice" ceiling so multi-series endpoints
    // land at distinct heights rather than converging to the same point.
    $built = [];
    foreach ( $series as $idx => $s ) {
        $nm = ! empty( $s['values'] )
            ? $nice_max_fn( (float) max( $s['values'] ) )
            : (float) $s['max'];

        $pts = [];
        foreach ( $s['values'] as $i => $v ) {
            $d     = isset( $days[ $i ] ) ? $days[ $i ] : 0;
            $pts[] = [ $X( $d ), $baseY - ( $v / $nm ) * $plotH ];
        }
        $line = glc_stats_smooth_path( $pts );
        $last = end( $pts );
        $area = $line . sprintf( ' L%.2f %.2f L%.2f %.2f Z', $last[0], $baseY, $pts[0][0], $baseY );
        $gid  = 'glcg-' . $idx . '-' . preg_replace( '/[^a-z0-9]/', '', $s['key'] );
        $built[] = array_merge( $s, [
            'pts'  => $pts, 'line' => $line, 'area' => $area,
            'last' => $last, 'gid'  => $gid,  'nm'   => $nm,
        ] );
    }

    // Compute grid lines and Y-axis label data.
    $grid_ys      = [];
    $left_labels  = [];
    $right_labels = [];

    if ( $show_axes && ! empty( $built ) ) {
        $nm0  = $built[0]['nm'];
        $step = $nice_step_fn( $nm0 );
        for ( $v = $step; $v <= $nm0 + $step * 0.01; $v += $step ) {
            $gy          = round( $baseY - ( $v / $nm0 ) * $plotH, 2 );
            $grid_ys[]   = $gy;
            $lv          = ( $v == floor( $v ) ) ? number_format( (int) $v ) : number_format( $v, 1 );
            $left_labels[] = [ 'y' => $gy, 'text' => $lv, 'color' => $built[0]['color'] ];
            if ( count( $built ) >= 2 ) {
                $rv             = (int) round( ( $v / $nm0 ) * $built[1]['nm'] );
                $right_labels[] = [ 'y' => $gy, 'text' => number_format( $rv ), 'color' => $built[1]['color'] ];
            }
        }
    } else {
        foreach ( [ 0.25, 0.5, 0.75, 1.0 ] as $f ) {
            $grid_ys[] = round( $baseY - $f * $plotH, 2 );
        }
    }

    // X-axis ticks: first date, each month boundary, "Now".
    $ticks = [];
    if ( $first_ts ) {
        $ticks[] = [ 'x' => $X( 0 ), 'label' => date( 'M j', $first_ts ), 'anchor' => 'start' ];
        $fY      = (int) date( 'Y', $first_ts );
        $fM      = (int) date( 'n', $first_ts );
        $last_ts = $first_ts + $max_day * 86400;
        for ( $mo = 1; $mo <= 18; $mo++ ) {
            $cy = $fY + (int) floor( ( $fM - 1 + $mo ) / 12 );
            $cm = ( ( $fM - 1 + $mo ) % 12 ) + 1;
            $ts = mktime( 0, 0, 0, $cm, 1, $cy );
            if ( $ts >= $last_ts ) break;
            $day_off = (int) round( ( $ts - $first_ts ) / 86400 );
            if ( $day_off < 12 || $day_off > $max_day - 12 ) continue;
            $ticks[] = [ 'x' => $X( $day_off ), 'label' => date( 'M', $ts ), 'anchor' => 'middle' ];
        }
        $ticks[] = [ 'x' => $X( $max_day ), 'label' => 'Now', 'anchor' => 'end' ];
    }

    ob_start();
    ?>
    <svg viewBox="0 0 <?php echo $W; ?> <?php echo $H; ?>" width="100%" preserveAspectRatio="none"
         style="display:block;overflow:visible" role="img" aria-hidden="true">
        <defs>
            <?php foreach ( $built as $b ) : ?>
            <linearGradient id="<?php echo esc_attr( $b['gid'] ); ?>" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%"   stop-color="<?php echo esc_attr( $b['color'] ); ?>" stop-opacity="<?php echo esc_attr( $b['fill_opacity'] ); ?>"/>
                <stop offset="100%" stop-color="<?php echo esc_attr( $b['color'] ); ?>" stop-opacity="0.02"/>
            </linearGradient>
            <?php endforeach; ?>
        </defs>

        <?php foreach ( $grid_ys as $gy ) : ?>
        <line x1="<?php echo $padL; ?>" x2="<?php echo $W - $padR; ?>"
              y1="<?php echo $gy; ?>" y2="<?php echo $gy; ?>"
              stroke="#1a4a6b" stroke-opacity="0.07" stroke-width="1"/>
        <?php endforeach; ?>

        <?php foreach ( $built as $b ) : ?>
        <path d="<?php echo esc_attr( $b['area'] ); ?>"
              fill="url(#<?php echo esc_attr( $b['gid'] ); ?>)"
              class="glc-area-in"/>
        <path d="<?php echo esc_attr( $b['line'] ); ?>"
              fill="none" stroke="<?php echo esc_attr( $b['color'] ); ?>"
              stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"
              pathLength="2600"
              class="glc-line-draw"/>
        <circle cx="<?php echo round( $b['last'][0], 2 ); ?>"
                cy="<?php echo round( $b['last'][1], 2 ); ?>"
                r="5" fill="#fff"
                stroke="<?php echo esc_attr( $b['color'] ); ?>" stroke-width="3"
                class="glc-dot-in"/>
        <?php endforeach; ?>

        <?php if ( $show_axes ) : ?>
        <?php foreach ( $left_labels as $ll ) : ?>
        <text x="<?php echo $padL - 6; ?>" y="<?php echo $ll['y'] + 4; ?>"
              text-anchor="end" font-family="'Lato',sans-serif" font-size="13"
              fill="<?php echo esc_attr( $ll['color'] ); ?>" opacity="0.65">
            <?php echo esc_html( $ll['text'] ); ?>
        </text>
        <?php endforeach; ?>
        <?php foreach ( $right_labels as $rl ) : ?>
        <text x="<?php echo $W - $padR + 6; ?>" y="<?php echo $rl['y'] + 4; ?>"
              text-anchor="start" font-family="'Lato',sans-serif" font-size="13"
              fill="<?php echo esc_attr( $rl['color'] ); ?>" opacity="0.65">
            <?php echo esc_html( $rl['text'] ); ?>
        </text>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php foreach ( $ticks as $tk ) : ?>
        <text x="<?php echo round( $tk['x'], 2 ); ?>" y="<?php echo $H - 10; ?>"
              text-anchor="<?php echo esc_attr( $tk['anchor'] ); ?>"
              font-family="'Lato',sans-serif" font-size="15" fill="#7d8893" font-weight="700">
            <?php echo esc_html( $tk['label'] ); ?>
        </text>
        <?php endforeach; ?>
    </svg>
    <?php
    return ob_get_clean();
}

// Image mapper for wildlife observations — returns filename relative to
// assets/images, or null. Shared by page-stats.php (wildlife cards + map
// pins) and the single cleanup_event / glc_submission templates.
function glc_stats_wildlife_img( $obs ) {
	$obs = strtolower( $obs );
	if ( strpos( $obs, 'beaver' ) !== false ) return 'beaver.png';
	if ( strpos( $obs, 'heron' ) !== false ) return 'heron.png';
	if ( strpos( $obs, 'toad' ) !== false ) return 'toad.png';
	if ( strpos( $obs, 'frog' ) !== false ) return 'frog.png';
	if ( strpos( $obs, 'butterfly' ) !== false ) return 'butterfly.png';
	if ( strpos( $obs, 'dragonfly' ) !== false ) return 'dragonfly.png';
	if ( strpos( $obs, 'cormorant' ) !== false ) return 'cormorant.png';
	if ( strpos( $obs, 'redwinged' ) !== false ) return 'redwinged.png';
	if ( strpos( $obs, 'mink' ) !== false ) return 'mink.png';
	if ( strpos( $obs, 'swallow' ) !== false ) return 'swallow.png';
	if ( strpos( $obs, 'snapping' ) !== false ) return 'snapping-turtle.png';
	if ( strpos( $obs, 'painted' )  !== false ) return 'painted-turtle.png';
	if ( strpos( $obs, 'minnows' ) !== false ) return 'minnows.png';
	if ( strpos( $obs, 'egg' )    !== false ) return 'nest.png';
	if ( strpos( $obs, 'merganser' )    !== false ) return 'merganser.png';
	if ( strpos( $obs, 'duck' )    !== false ) return 'duck.png';
	if ( strpos( $obs, 'goose' )    !== false
	  || strpos( $obs, 'geese' )    !== false ) return 'canada-goose.png';
	if ( strpos( $obs, 'snake' )    !== false ) return 'snake.png';
	if ( strpos( $obs, 'leech' )    !== false ) return 'leech.png';
	if ( strpos( $obs, 'sandpiper' )    !== false ) return 'sandpiper.png';
	return null;
}
