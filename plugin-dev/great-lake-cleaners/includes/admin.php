<?php
/**
 * Admin UX improvements:
 * - Custom columns in the cleanup list screen
 * - Sortable date column
 * - Admin styles
 */
defined( 'ABSPATH' ) || exit;

// ── List table columns ────────────────────────────────────────────────────────

add_filter( 'manage_cleanup_event_posts_columns', function( $cols ) {
    unset( $cols['date'] );
    return array_merge( $cols, [
        'cleanup_date' => 'Date',
        'site_name'    => 'Site',
        'corridor'     => 'Corridor',
        'volunteers'   => 'Volunteers',
        'bags'         => 'Bags',
        'weight_kg'    => 'kg',
        'tires'        => 'Tires',
        'hazards'      => 'Haz. Waste',
        'planted'      => 'Plants',
    ] );
} );

add_action( 'manage_cleanup_event_posts_custom_column', function( $col, $post_id ) {
    switch ( $col ) {
        case 'cleanup_date':
            $d = get_post_meta( $post_id, 'cleanup_date', true );
            echo $d ? esc_html( date( 'M j, Y', strtotime( $d ) ) ) : '—';
            break;
        case 'site_name':
            echo esc_html( get_post_meta( $post_id, 'site_name', true ) ?: get_the_title( $post_id ) );
            break;
        case 'volunteers':
            echo esc_html( get_post_meta( $post_id, 'volunteers', true ) ?: '—' );
            break;
        case 'bags':
            echo esc_html( get_post_meta( $post_id, 'bags', true ) ?: '—' );
            break;
        case 'weight_kg':
            $w = get_post_meta( $post_id, 'weight_kg', true );
            echo $w ? esc_html( number_format( $w, 0 ) ) . ' kg' : '—';
            break;
        case 'corridor':
            echo esc_html( get_post_meta( $post_id, 'corridor', true ) ?: '—' );
            break;
        case 'tires':
            $t = get_post_meta( $post_id, 'tires_removed', true );
            echo $t ? esc_html( $t ) : '—';
            break;
        case 'hazards':
            $h = get_post_meta( $post_id, 'hazards_removed', true );
            echo $h ? esc_html( $h ) : '—';
            break;
        case 'planted':
            $p = get_post_meta( $post_id, 'species_planted', true );
            echo $p ? esc_html( $p ) : '—';
            break;
    }
}, 10, 2 );

// Make cleanup_date column sortable
add_filter( 'manage_edit-cleanup_event_sortable_columns', function( $cols ) {
    $cols['cleanup_date'] = 'cleanup_date';
    return $cols;
} );

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'cleanup_event' ) return;
    if ( $query->get( 'orderby' ) === 'cleanup_date' ) {
        $query->set( 'meta_key', 'cleanup_date' );
        $query->set( 'orderby', 'meta_value' );
    }
    // Default order: newest cleanup first
    if ( ! $query->get( 'orderby' ) ) {
        $query->set( 'meta_key', 'cleanup_date' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'DESC' );
    }
} );

// ── Gallery feature flag on attachments ──────────────────────────────────────
// Adds a "Feature in photo gallery" checkbox to the attachment edit modal.
// Only attachments with _glc_gallery = '1' appear in [glc_gallery].

add_filter( 'attachment_fields_to_edit', function( $fields, $post ) {
    $checked = get_post_meta( $post->ID, '_glc_gallery', true ) === '1';
    $fields['glc_gallery'] = [
        'label' => __( 'Gallery', 'great-lake-cleaners' ),
        'input' => 'html',
        'html'  => '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
                 . '<input type="checkbox" name="attachments[' . esc_attr( $post->ID ) . '][glc_gallery]" value="1"'
                 . ( $checked ? ' checked' : '' ) . '>'
                 . esc_html__( 'Feature in photo gallery', 'great-lake-cleaners' )
                 . '</label>',
        'helps' => esc_html__( 'Show this photo on the Photos page.', 'great-lake-cleaners' ),
    ];
    return $fields;
}, 10, 2 );

add_filter( 'attachment_fields_to_save', function( $post, $attachment ) {
    update_post_meta( $post['ID'], '_glc_gallery', ! empty( $attachment['glc_gallery'] ) ? '1' : '0' );
    return $post;
}, 10, 2 );

// ── Admin styles ─────────────────────────────────────────────────────────────

add_action( 'admin_head', function() {
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'cleanup_event' ) === false ) return;
    ?>
    <style>
    .column-volunteers, .column-bags, .column-weight_kg, .column-tires, .column-hazards, .column-planted { width: 70px; text-align: center; }
    .column-cleanup_date { width: 110px; }
    .column-site_name { width: 220px; }
    .column-corridor { width: 140px; }
    </style>
    <?php
} );

// ── Front-end styles (inline, keeps plugin self-contained) ───────────────────

add_action( 'wp_head', function() {
    if ( ! is_singular( 'cleanup_event' ) &&
         ! is_post_type_archive( 'cleanup_event' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_stats' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_map' ) &&
         ! has_shortcode( get_post()->post_content ?? '', 'glc_archive' ) ) {
        return;
    }
    ?>
    <style>
    /* Stats banner */
    .glc-stats-banner {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        justify-content: center;
        padding: 24px;
        background: #124e4c;
        border-radius: 10px;
        margin: 24px 0;
    }
    .glc-stat {
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 100px;
    }
    .glc-stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #a2d5ab;
        line-height: 1;
    }
    .glc-stat-label {
        font-size: 0.8rem;
        color: #a0c8c3;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 4px;
    }

    /* Archive cards */
    .glc-archive { display: flex; flex-direction: column; gap: 16px; }
    .glc-event-card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 16px 20px;
        background: #fafafa;
    }
    .glc-event-date { font-size: 0.85rem; color: #666; }
    .glc-event-title { margin: 4px 0 10px; font-size: 1.15rem; }
    .glc-event-title a { text-decoration: none; color: #124e4c; }
    .glc-event-title a:hover { text-decoration: underline; }
    .glc-event-stats { display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.9rem; color: #444; }
    .glc-event-wildlife { font-size: 0.9rem; color: #555; margin: 8px 0 0; }

    /* Map */
    .glc-map { border: 1px solid #ccc; }
    </style>
    <?php
} );
