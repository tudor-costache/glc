<?php
/**
 * Great Lake Cleaners — functions.php
 * Theme setup: nav menus, featured images, font enqueue, body class helpers.
 */

defined( 'ABSPATH' ) || exit;

define( 'GLC_THEME_VERSION', '1.0.0' );

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

    // Mobile nav toggle script
    wp_enqueue_script(
        'glc-nav',
        get_stylesheet_directory_uri() . '/assets/js/nav.js',
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
 *   int    $corridors  Distinct corridor values across all cleanup_event posts
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
    return $classes;
} );
